<?php
/* ============================================================
   guardar_pedido_entradas.php — Crea pedido PENDIENTE + items
   - Valida evento (eventos_deportivos)
   - Lee tipos visibles online
   - Guarda comprobante (opcional)
   - Inserta pedido en estado 'pendiente' (sin descontar stock)
   - Inserta items del pedido
   - FK de pedidos.evento_id -> eventos_deportivos(id)
   - Tablas mínimas: pedidos, pedidos_items, eventos_cobros
   ============================================================ */

if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)){ $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_table(mysqli $db, string $t): bool {
  $t=$db->real_escape_string($t);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' LIMIT 1";
  if ($r=$db->query($sql)){ $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function save_upload(?array $f, int $evento_id): ?string {
  if (!$f || ($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return null;
  $dir = __DIR__."/uploads/comprobantes/evento_".$evento_id;
  if (!is_dir($dir)) @mkdir($dir,0775,true);
  $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext,['pdf','jpg','jpeg','png','webp'])) $ext='pdf';
  $dest = $dir.'/comp_'.time().'_'.mt_rand(1000,9999).'.'.$ext;
  if (!@move_uploaded_file($f['tmp_name'],$dest)) return null;
  return 'uploads/comprobantes/evento_'.$evento_id.'/'.basename($dest);
}
function email_ok($e){ return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }

/* ---------- Esquema mínimo + Fix FK pedidos → eventos_deportivos ---------- */
$conexion->query("CREATE TABLE IF NOT EXISTS `eventos_cobros` (
  `evento_id` INT NOT NULL PRIMARY KEY,
  `alias_cbu` VARCHAR(120) NULL,
  `cuenta_destino` VARCHAR(120) NULL,
  CONSTRAINT `fk_cobros_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos_deportivos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conexion->query("CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `evento_id` INT NOT NULL,
  `comprador_nombre` VARCHAR(120) NOT NULL,
  `comprador_email` VARCHAR(160) NOT NULL,
  `comprador_tel` VARCHAR(60) NULL,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` ENUM('efectivo','transferencia','tarjeta','qr','otro') NOT NULL DEFAULT 'transferencia',
  `alias_usado` VARCHAR(120) NULL,
  `cuenta_destino` VARCHAR(120) NULL,
  `comprobante_path` VARCHAR(255) NULL,
  `estado` ENUM('pendiente','aprobado','rechazado','pagado') NOT NULL DEFAULT 'pendiente',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conexion->query("CREATE TABLE IF NOT EXISTS `pedidos_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT NOT NULL,
  `tipo_id` INT NOT NULL,
  `cantidad` INT NOT NULL DEFAULT 1,
  `precio_unit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_items_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos`(`id`) ON DELETE CASCADE,
  INDEX (`tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Migraciones suaves (por si faltan columnas en pedidos) */
$migs = [
  ['metodo_pago',      "ALTER TABLE `pedidos` ADD COLUMN `metodo_pago` ENUM('efectivo','transferencia','tarjeta','qr','otro') NOT NULL DEFAULT 'transferencia' AFTER `total`"],
  ['alias_usado',      "ALTER TABLE `pedidos` ADD COLUMN `alias_usado` VARCHAR(120) NULL AFTER `metodo_pago`"],
  ['cuenta_destino',   "ALTER TABLE `pedidos` ADD COLUMN `cuenta_destino` VARCHAR(120) NULL AFTER `alias_usado`"],
  ['comprobante_path', "ALTER TABLE `pedidos` ADD COLUMN `comprobante_path` VARCHAR(255) NULL AFTER `cuenta_destino`"],
  ['estado',           "ALTER TABLE `pedidos` ADD COLUMN `estado` ENUM('pendiente','aprobado','rechazado','pagado') NOT NULL DEFAULT 'pendiente' AFTER `comprobante_path`"],
];
foreach($migs as [$col,$sql]){ if(!has_col($conexion,'pedidos',$col)){ @$conexion->query($sql); } }

/* Fix FK de pedidos.evento_id si apunta a otra tabla */
$q = "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='pedidos'
        AND COLUMN_NAME='evento_id'
        AND REFERENCED_TABLE_NAME IS NOT NULL
      LIMIT 1";
if ($r=$conexion->query($q)) {
  if ($fk=$r->fetch_assoc()) {
    $ref = (string)$fk['REFERENCED_TABLE_NAME'];
    $cst = (string)$fk['CONSTRAINT_NAME'];
    if ($ref !== 'eventos_deportivos') {
      @ $conexion->query("ALTER TABLE `pedidos` DROP FOREIGN KEY `{$cst}`");
    }
  }
  $r->close();
}
@ $conexion->query("ALTER TABLE `pedidos` ADD INDEX (`evento_id`)");
@ $conexion->query("ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_fk_evento_deportivos`
  FOREIGN KEY (`evento_id`) REFERENCES `eventos_deportivos`(`id`) ON DELETE CASCADE");

/* ---------- INPUT ---------- */
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){ http_response_code(405); exit('POST'); }
$evento_id = (int)($_POST['evento_id']??0);
$nombre = trim((string)($_POST['nombre']??''));
$email  = trim((string)($_POST['email']??''));
$tel    = trim((string)($_POST['tel']??''));
$metodo = trim((string)($_POST['metodo_pago']??'transferencia'));
$qtyRaw = $_POST['qty']??[];

if ($evento_id<=0){ $_SESSION['flash_error']='Falta evento.'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }
if ($nombre===''){ $_SESSION['flash_error']='Ingresá tu nombre.'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }
if ($email==='' || !email_ok($email)){ $_SESSION['flash_error']='Email inválido.'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }

/* Validar evento real en eventos_deportivos */
$st=$conexion->prepare("SELECT id FROM eventos_deportivos WHERE id=? LIMIT 1");
$st->bind_param('i',$evento_id); $st->execute();
$okEv = $st->get_result()->num_rows>0; $st->close();
if (!$okEv){ $_SESSION['flash_error']='Evento no válido.'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }

/* normalizar cantidades */
$qty=[]; foreach($qtyRaw as $tid=>$q){ if(ctype_digit((string)$tid) && ctype_digit((string)$q)){ $qty[(int)$tid]=(int)$q; } }
if (!$qty || array_sum($qty)<=0){ $_SESSION['flash_error']='Seleccioná al menos 1 entrada.'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }

/* tipos elegidos (solo visibles online/todos) */
$ids=array_keys($qty);
$ph = implode(',', array_fill(0,count($ids),'?'));
$types = str_repeat('i', count($ids)+1);
$params=$ids; array_unshift($params,$evento_id);

$sql="SELECT id,nombre,precio,stock_disponible,max_por_compra
      FROM tickets_tipos
      WHERE evento_id=? AND id IN ($ph) AND visible=1 AND (canal IN('online','todos'))";
$st = $conexion->prepare($sql);
if(!$st){ $_SESSION['flash_error']='Error interno (prep).'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }
$bind=[$types]; foreach($params as $k=>$v){ $bind[]=&$params[$k]; }
call_user_func_array([$st,'bind_param'],$bind);
$st->execute();
$rs=$st->get_result(); $tipos=[];
while($row=$rs->fetch_assoc()){ $tipos[(int)$row['id']]=$row; }
$st->close();

/* validar cantidades y calcular total (no afecta stock aquí) */
$total=0.0;
foreach($qty as $tid=>$q){
  if($q<=0) continue;
  if(!isset($tipos[$tid])){ $_SESSION['flash_error']='Tipo inválido o no disponible online.'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }
  if($q > (int)$tipos[$tid]['max_por_compra']){
    $_SESSION['flash_error']='Máximo por compra superado en '.$tipos[$tid]['nombre'];
    header('Location: comprar_entradas.php?evento_id='.$evento_id); exit;
  }
  // chequeo soft de stock actual
  if($q > (int)$tipos[$tid]['stock_disponible']){
    $_SESSION['flash_error']='Sin stock suficiente en '.$tipos[$tid]['nombre'];
    header('Location: comprar_entradas.php?evento_id='.$evento_id); exit;
  }
  $total += (float)$tipos[$tid]['precio']*$q;
}

/* datos de cobro (alias/cuenta visibles) */
$cobro = [];
if ($st=$conexion->prepare("SELECT alias_cbu,cuenta_destino FROM eventos_cobros WHERE evento_id=?")){
  $st->bind_param('i',$evento_id); $st->execute();
  $cobro=$st->get_result()->fetch_assoc() ?: []; $st->close();
}
$alias = (string)($cobro['alias_cbu']??'');
$cuenta= (string)($cobro['cuenta_destino']??'');

/* guardar comprobante (opcional) */
$comprobante = save_upload($_FILES['comprobante'] ?? null, $evento_id);

/* Insertar pedido PENDIENTE + items (sin tocar stock, sin tickets) */
$conexion->begin_transaction();
try{
  $sql="INSERT INTO pedidos(evento_id,comprador_nombre,comprador_email,comprador_tel,total,metodo_pago,alias_usado,cuenta_destino,comprobante_path,estado)
        VALUES(?,?,?,?,?,?,?,?,?,'pendiente')";
  $st=$conexion->prepare($sql);
  if(!$st){ throw new Exception('prep pedido'); }
  $st->bind_param('isssdssss',$evento_id,$nombre,$email,$tel,$total,$metodo,$alias,$cuenta,$comprobante);
  if(!$st->execute()) throw new Exception('exec pedido: '.$conexion->error);
  $pedido_id=(int)$st->insert_id; $st->close();

  $sqlI="INSERT INTO pedidos_items(pedido_id,tipo_id,cantidad,precio_unit) VALUES(?,?,?,?)";
  $sti=$conexion->prepare($sqlI);
  if(!$sti){ throw new Exception('prep items'); }
  foreach($qty as $tid=>$q){
    if($q<=0) continue;
    $pu=(float)$tipos[$tid]['precio'];
    $sti->bind_param('iiid',$pedido_id,$tid,$q,$pu);
    if(!$sti->execute()) throw new Exception('exec items: '.$conexion->error);
  }
  $sti->close();

  $conexion->commit();
  $_SESSION['ok_msg']='Solicitud enviada. Te avisaremos por email cuando se apruebe el pago.';
  header('Location: compra_ok.php?pedido_id='.$pedido_id);
  exit;

}catch(Exception $e){
  $conexion->rollback();
  $_SESSION['flash_error']='Error: '.$e->getMessage();
  header('Location: comprar_entradas.php?evento_id='.$evento_id);
  exit;
}
