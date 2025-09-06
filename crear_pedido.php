<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function code_gen(int $len=12): string {
  $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $n=strlen($a); $s='';
  for($i=0;$i<$len;$i++){ $s.=$a[random_int(0,$n-1)]; }
  return $s;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* ===== Asegura columnas para QR por venta ===== */
if (!has_col($conexion,'pedidos','qr_token')) {
  @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `qr_token` CHAR(40) UNIQUE NULL");
}
if (!has_col($conexion,'pedidos','qr_status')) {
  @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `qr_status` ENUM('activo','usado') NOT NULL DEFAULT 'activo'");
}
if (!has_col($conexion,'pedidos','qr_used_at')) {
  @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `qr_used_at` DATETIME NULL");
}

/* ===== INPUT ===== */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('POST'); }
$evento_id = isset($_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;
$nombre = trim((string)($_POST['nombre'] ?? ''));
$email  = trim((string)($_POST['email'] ?? ''));
$tel    = trim((string)($_POST['tel'] ?? ''));
$qtyRaw = $_POST['qty'] ?? [];
if ($evento_id<=0 || $nombre==='' || $email===''){
  $_SESSION['flash_error']='Completar datos y cantidades.';
  header('Location: evento.php?id='.$evento_id);
  exit;
}

/* normalizamos cantidades */
$qty = [];
foreach($qtyRaw as $tipo_id=>$q){
  if (ctype_digit((string)$tipo_id) && ctype_digit((string)$q)) {
    $qty[(int)$tipo_id]=(int)$q;
  }
}
if (!$qty) { $_SESSION['flash_error']='Seleccioná al menos 1 entrada.'; header('Location: evento.php?id='.$evento_id); exit; }
$totCant = array_sum($qty);
if ($totCant<=0){ $_SESSION['flash_error']='Seleccioná cantidad > 0.'; header('Location: evento.php?id='.$evento_id); exit; }

/* ===== TRAEMOS TIPOS Y CONTROLAMOS STOCK ===== */
$tipo_ids = array_keys($qty);
$ph = implode(',', array_fill(0,count($tipo_ids),'?'));
$types = str_repeat('i', count($tipo_ids)+1);
$params = $tipo_ids; array_unshift($params,$evento_id);

$sql="SELECT id,nombre,precio,stock_disponible,max_por_compra
      FROM tickets_tipos
      WHERE evento_id=? AND id IN ($ph)
      FOR UPDATE";

$conexion->begin_transaction();
try{
  $st=$conexion->prepare($sql);
  $bind=[$types]; foreach($params as $k=>$v){ $bind[]=&$params[$k]; }
  call_user_func_array([$st,'bind_param'],$bind);
  $st->execute();
  $rs=$st->get_result(); $tipos=[];
  while($row=$rs->fetch_assoc()){ $tipos[(int)$row['id']]=$row; }
  $st->close();

  $total=0.0;
  foreach($qty as $tid=>$q){
    if ($q<=0) continue;
    if (!isset($tipos[$tid])) throw new Exception('Tipo inválido');
    $t=$tipos[$tid];
    if ($q>$t['stock_disponible']) throw new Exception('Sin stock suficiente en '.$t['nombre']);
    if ($q>$t['max_por_compra'])   throw new Exception('Máx '.$t['max_por_compra'].' en '.$t['nombre']);
    $total += (float)$t['precio']*(int)$q;
  }

  /* ===== INSERT PEDIDO (venta) ===== */
  $st=$conexion->prepare("INSERT INTO pedidos (evento_id,comprador_nombre,comprador_email,comprador_tel,total,estado) VALUES (?,?,?,?,?,'pagado')");
  $st->bind_param('isssd',$evento_id,$nombre,$email,$tel,$total);
  $st->execute(); $pedido_id=$st->insert_id; $st->close();

  /* 🔐 Token QR por número de venta */
  $token = bin2hex(random_bytes(20)); // 40 chars hex
  $st=$conexion->prepare("UPDATE pedidos SET qr_token=?, qr_status='activo' WHERE id=?");
  $st->bind_param('si',$token,$pedido_id);
  $st->execute(); $st->close();

  /* ===== EMITIR TICKETS + DESCONTAR STOCK (sin generar QR ni archivos) ===== */
  foreach($qty as $tid=>$q){
    if ($q<=0) continue;

    // Descontar stock
    $st=$conexion->prepare("UPDATE tickets_tipos
                            SET stock_disponible=stock_disponible-?
                            WHERE id=? AND evento_id=? AND stock_disponible>=?");
    $st->bind_param('iiii',$q,$tid,$evento_id,$q);
    $st->execute();
    if ($st->affected_rows<=0){ $st->close(); throw new Exception('Stock cambió, reintentá.'); }
    $st->close();

    // Emitir tickets (códigos únicos) — NO se guarda QR en disco
    for($i=0;$i<$q;$i++){
      do{
        $code = code_gen(12);
        $chk=$conexion->prepare("SELECT 1 FROM tickets WHERE code=?");
        $chk->bind_param('s',$code);
        $chk->execute();
        $exists=$chk->get_result()->num_rows>0;
        $chk->close();
      }while($exists);

      $st=$conexion->prepare("INSERT INTO tickets (pedido_id,evento_id,tipo_id,code) VALUES (?,?,?,?)");
      $st->bind_param('iiis',$pedido_id,$evento_id,$tid,$code);
      $st->execute();
      $st->close();
    }
  }

  $conexion->commit();

  /* Redirige a confirmación. El PDF se descarga desde qr_evento.php (al vuelo, sin guardar). */
  $_SESSION['ok_msg']='Compra realizada. Descargá tu entrada en PDF.';
  header('Location: compra_ok.php?pedido_id='.$pedido_id);
  exit;

}catch(Exception $e){
  $conexion->rollback();
  $_SESSION['flash_error']=$e->getMessage();
  header('Location: evento.php?id='.$evento_id);
  exit;
}
