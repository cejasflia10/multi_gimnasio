<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
function code_gen(int $len=12): string {
  $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $n=strlen($a); $s='';
  for($i=0;$i<$len;$i++){ $s.=$a[random_int(0,$n-1)]; }
  return $s;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function app_url(string $p=''): string {
  $base = defined('APP_BASE_URL') ? rtrim(APP_BASE_URL,'/').'/' : ( (isset($_SERVER['HTTP_HOST'])?'https://'.$_SERVER['HTTP_HOST'].'/':'/') );
  return $base.ltrim($p,'/');
}
/** bind_param con cantidad dinámica (todas referencias) */
function bindParams(mysqli_stmt $st, string $types, array &$vals): bool {
  $a = [];
  $a[] = &$types;
  foreach ($vals as $k => &$v) { $a[] = &$v; }
  return call_user_func_array([$st,'bind_param'], $a);
}

/* ===== Migraciones suaves en pedidos ===== */
if (!has_col($conexion,'pedidos','qr_token'))      { @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `qr_token` CHAR(40) UNIQUE NULL AFTER total"); }
if (!has_col($conexion,'pedidos','qr_status'))     { @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `qr_status` ENUM('activo','usado') NOT NULL DEFAULT 'activo' AFTER qr_token"); }
if (!has_col($conexion,'pedidos','qr_used_at'))    { @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `qr_used_at` DATETIME NULL AFTER qr_status"); }
if (!has_col($conexion,'pedidos','comprador_tel')) { @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `comprador_tel` VARCHAR(60) NULL AFTER comprador_email"); }
if (!has_col($conexion,'pedidos','metodo_pago'))   { @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `metodo_pago` VARCHAR(30) NULL AFTER comprador_tel"); }
if (!has_col($conexion,'pedidos','origen'))        { @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `origen` ENUM('online','taquilla') NOT NULL DEFAULT 'online' AFTER total"); }
if (!has_col($conexion,'pedidos','comprobante_path')){ @$conexion->query("ALTER TABLE `pedidos` ADD COLUMN `comprobante_path` VARCHAR(255) NULL AFTER metodo_pago"); }

/* ===== INPUT ===== */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('POST'); }

$evento_id    = isset($_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;
$nombre       = trim((string)($_POST['nombre'] ?? ''));
$email        = trim((string)($_POST['email'] ?? ''));
$tel          = trim((string)($_POST['tel'] ?? ''));
$metodo_pago  = trim((string)($_POST['metodo_pago'] ?? '')); // transferencia / efectivo / tarjeta
$qtyRaw       = $_POST['qty'] ?? [];

if ($evento_id<=0 || $nombre==='' || $email===''){
  $_SESSION['flash_error']='Completá tus datos y seleccioná cantidades.';
  header('Location: evento.php?id='.$evento_id);
  exit;
}

/* Normalizar cantidades (solo >0) */
$qty = [];
foreach($qtyRaw as $tipo_id=>$q){
  if (ctype_digit((string)$tipo_id) && ctype_digit((string)$q)) {
    $qq = (int)$q;
    if ($qq>0) $qty[(int)$tipo_id]=$qq;
  }
}
if (!$qty) { $_SESSION['flash_error']='Seleccioná al menos 1 entrada (>0).'; header('Location: evento.php?id='.$evento_id); exit; }

/* ===== Traer tipos y controlar stock ===== */
$tipo_ids = array_keys($qty);
if (count($tipo_ids)===0){
  $_SESSION['flash_error']='No hay tipos de entrada válidos en la selección.';
  header('Location: evento.php?id='.$evento_id); exit;
}
$ph = implode(',', array_fill(0,count($tipo_ids),'?'));
$types = str_repeat('i', count($tipo_ids)+1); // +1 por evento_id
$params = $tipo_ids; array_unshift($params,$evento_id);

$sql="SELECT id,nombre,precio,stock_disponible,max_por_compra
      FROM tickets_tipos
      WHERE evento_id=? AND id IN ($ph)
      FOR UPDATE";

$conexion->begin_transaction();
try{
  $st=$conexion->prepare($sql);
  if (!$st) { throw new Exception('Error al preparar consulta de tipos: '.$conexion->error); }
  if (!bindParams($st, $types, $params)) { throw new Exception('Error bind_param tipos: '.$st->error); }
  $st->execute();
  $rs=$st->get_result(); $tipos=[];
  while($row=$rs->fetch_assoc()){ $tipos[(int)$row['id']]=$row; }
  $st->close();

  if (count($tipos)!==count($tipo_ids)) { throw new Exception('Algún tipo seleccionado no existe.'); }

  $total=0.0;
  foreach($qty as $tid=>$q){
    $t=$tipos[$tid];
    if ($q>$t['stock_disponible']) throw new Exception('Sin stock suficiente en “'.$t['nombre'].'”.');
    if ((int)$t['max_por_compra']>0 && $q>$t['max_por_compra']) throw new Exception('Máximo '.$t['max_por_compra'].' en “'.$t['nombre'].'”.');
    $total += (float)$t['precio']*(int)$q;
  }

  /* ===== Insert pedido (estado PENDIENTE) ===== */
  $sqlP = "INSERT INTO pedidos (evento_id,comprador_nombre,comprador_email,comprador_tel,metodo_pago,total,estado,origen)
           VALUES (?,?,?,?,?,?,'pendiente','online')";
  $st=$conexion->prepare($sqlP);
  if (!$st) { throw new Exception('Error al preparar pedido: '.$conexion->error); }
  $st->bind_param('issssd',$evento_id,$nombre,$email,$tel,$metodo_pago,$total);
  $st->execute(); $pedido_id=$st->insert_id; $st->close();

  /* Token QR por número de venta (para PDF/consulta pública) */
  $token = bin2hex(random_bytes(20)); // 40 chars hex
  $st=$conexion->prepare("UPDATE pedidos SET qr_token=?, qr_status='activo' WHERE id=?");
  if (!$st) { throw new Exception('Error actualizando token: '.$conexion->error); }
  $st->bind_param('si',$token,$pedido_id);
  $st->execute(); $st->close();

  /* Guardar comprobante si viene (imagen o PDF) */
  if (!empty($_FILES['comprobante']) && ($_FILES['comprobante']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['comprobante']['tmp_name'];
    $name = basename((string)$_FILES['comprobante']['name']);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','pdf'])) $ext = 'jpg';
    $dir = __DIR__ . '/uploads/comprobantes';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $dest = $dir . '/pedido_'.$pedido_id.'_'.time().'.'.$ext;
    if (@move_uploaded_file($tmp, $dest)) {
      $rel = 'uploads/comprobantes/'.basename($dest);
      $st=$conexion->prepare("UPDATE pedidos SET comprobante_path=? WHERE id=?");
      if ($st) { $st->bind_param('si',$rel,$pedido_id); $st->execute(); $st->close(); }
    }
  }

  /* ===== Emitir tickets y descontar stock ===== */
  foreach($qty as $tid=>$q){
    // Descontar stock
    $st=$conexion->prepare("UPDATE tickets_tipos
                            SET stock_disponible=stock_disponible-?
                            WHERE id=? AND evento_id=? AND stock_disponible>=?");
    if (!$st) { throw new Exception('Error al preparar descuento stock: '.$conexion->error); }
    $st->bind_param('iiii',$q,$tid,$evento_id,$q);
    $st->execute();
    if ($st->affected_rows<=0){ $st->close(); throw new Exception('El stock cambió, volvé a intentar.'); }
    $st->close();

    // Emitir N tickets
    for($i=0;$i<$q;$i++){
      do{
        $code = code_gen(12);
        $chk=$conexion->prepare("SELECT 1 FROM tickets WHERE code=?");
        if (!$chk) { throw new Exception('Error check code: '.$conexion->error); }
        $chk->bind_param('s',$code);
        $chk->execute();
        $exists=$chk->get_result()->num_rows>0;
        $chk->close();
      }while($exists);

      $ins=$conexion->prepare("INSERT INTO tickets (pedido_id,evento_id,tipo_id,code) VALUES (?,?,?,?)");
      if (!$ins) { throw new Exception('Error insert ticket: '.$conexion->error); }
      $ins->bind_param('iiis',$pedido_id,$evento_id,$tid,$code);
      $ins->execute();
      $ins->close();
    }
  }

  $conexion->commit();

  /* ====== WA al organizador si hay número cargado ====== */
  // (necesitás tener creada la tabla eventos_contactos_config con campo whatsapp_admin)
  $orgWa = '';
  $conexion->query("CREATE TABLE IF NOT EXISTS eventos_contactos_config (
    evento_id INT PRIMARY KEY,
    whatsapp_admin VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos_deportivos(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  if ($st = $conexion->prepare("SELECT whatsapp_admin FROM eventos_contactos_config WHERE evento_id=? LIMIT 1")){
    $st->bind_param('i',$evento_id); $st->execute();
    $res = $st->get_result()->fetch_assoc(); $st->close();
    if ($res && !empty($res['whatsapp_admin'])) { $orgWa = preg_replace('/\D+/', '', $res['whatsapp_admin']); }
  }

  $evento = $conexion->query("SELECT titulo FROM eventos_deportivos WHERE id=".(int)$evento_id." LIMIT 1")->fetch_assoc();
  $evtTitulo = $evento['titulo'] ?? ('Evento #'.$evento_id);

  // Texto para el organizador (incluye posible comprobante y link a gestión)
  $detalle = [];
  foreach($qty as $tid=>$q){
    $t = $tipos[$tid];
    $detalle[] = '- '.($t['nombre']??('Tipo '.$tid)).' x '.$q.' ($ '.number_format((float)$t['precio'],2,',','.').' c/u)';
  }
  $comp = '';
  if (!empty($rel)) { $comp = app_url($rel); }

  $waTxt  = "📲 *Nuevo pedido #{$pedido_id}*%0A";
  $waTxt .= "*Evento:* ".rawurlencode($evtTitulo)."%0A";
  $waTxt .= "*Cliente:* ".rawurlencode($nombre)." | ".rawurlencode($email)." | ".rawurlencode($tel)."%0A";
  $waTxt .= "*Pago:* ".rawurlencode($metodo_pago)."%0A";
  $waTxt .= "*Total:* $ ".number_format($total,2,',','.')."%0A";
  $waTxt .= "*Detalle:*%0A".rawurlencode(implode("%0A",$detalle))."%0A";
  if ($comp) { $waTxt .= "*Comprobante:* ".rawurlencode($comp)."%0A"; }
  $waTxt .= "▶️ Revisar: ".rawurlencode(app_url('ver_ventas_evento.php?evento_id='.$evento_id));

  $waLink = $orgWa ? ('https://wa.me/'.$orgWa.'?text='.$waTxt) : '';

  $_SESSION['ok_msg'] = 'Pedido recibido. Te avisamos por email cuando se acredite el pago.'
    . ($waLink ? '<br>También podés avisar al organizador por WhatsApp: <a target="_blank" rel="noopener" href="'.h($waLink).'">Enviar</a>' : '');

  header('Location: compra_ok.php?pedido_id='.$pedido_id);
  exit;

} catch(Exception $e) {
  $conexion->rollback();
  $_SESSION['flash_error']='Error: '.$e->getMessage();
  header('Location: evento.php?id='.$evento_id);
  exit;
}
