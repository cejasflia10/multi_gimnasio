<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
@$conexion->set_charset('utf8mb4');
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if($r=$db->query($sql)){ $ok=(bool)$r->num_rows; $r->close(); return $ok; } return false;
}
function save_upload(?array $f, int $evento_id): ?string {
  if (!$f || ($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return null;
  $dir = __DIR__."/uploads/comprobantes/evento_".$evento_id;
  if (!is_dir($dir)) @mkdir($dir,0775,true);
  $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext,['pdf','jpg','jpeg','png','webp'])) $ext='pdf';
  $fname = 'comp_'.time().'_'.mt_rand(1000,9999).'.'.$ext;
  if (!@move_uploaded_file($f['tmp_name'],$dir.'/'.$fname)) return null;
  return 'uploads/comprobantes/evento_'.$evento_id.'/'.$fname;
}

/* Reqs */
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){ http_response_code(405); exit('POST'); }
$evento_id = (int)($_POST['evento_id']??0);
$nombre = trim((string)($_POST['nombre']??''));
$email  = trim((string)($_POST['email']??''));
$tel    = trim((string)($_POST['tel']??''));
$metodo = trim((string)($_POST['metodo_pago']??'transferencia'));
$qtyRaw = $_POST['qty']??[];

if ($evento_id<=0 || $nombre==='' || $email===''){ $_SESSION['flash_error']='Completá tus datos.'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }

/* Migraciones suaves */
if (!has_col($conexion,'pedidos','origen'))           { @$conexion->query("ALTER TABLE pedidos ADD COLUMN origen ENUM('online','taquilla') NOT NULL DEFAULT 'online' AFTER total"); }
if (!has_col($conexion,'pedidos','metodo_pago'))      { @$conexion->query("ALTER TABLE pedidos ADD COLUMN metodo_pago VARCHAR(50) NULL AFTER origen"); }
if (!has_col($conexion,'pedidos','alias_usado'))      { @$conexion->query("ALTER TABLE pedidos ADD COLUMN alias_usado VARCHAR(120) NULL AFTER metodo_pago"); }
if (!has_col($conexion,'pedidos','cuenta_destino'))   { @$conexion->query("ALTER TABLE pedidos ADD COLUMN cuenta_destino VARCHAR(200) NULL AFTER alias_usado"); }
if (!has_col($conexion,'pedidos','comprobante_path')) { @$conexion->query("ALTER TABLE pedidos ADD COLUMN comprobante_path VARCHAR(255) NULL AFTER cuenta_destino"); }
if (!has_col($conexion,'pedidos','estado'))           { @$conexion->query("ALTER TABLE pedidos ADD COLUMN estado ENUM('pendiente','aprobado','pagado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente' AFTER comprobante_path"); }
if (!has_col($conexion,'pedidos','created_at'))       { @$conexion->query("ALTER TABLE pedidos ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"); }
$conexion->query("CREATE TABLE IF NOT EXISTS pedidos_items(
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  tipo_id INT NOT NULL,
  cantidad INT NOT NULL,
  precio_unit DECIMAL(10,2) NOT NULL,
  FOREIGN KEY(pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Tipos elegidos (solo online/todos) */
$qty=[]; foreach($qtyRaw as $tid=>$q){ if(ctype_digit((string)$tid)&&ctype_digit((string)$q)){ $q=(int)$q; if($q>0)$qty[(int)$tid]=$q; } }
if (!$qty){ $_SESSION['flash_error']='Seleccioná al menos 1 entrada.'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }

$ids=array_keys($qty);
$ph=implode(',',array_fill(0,count($ids),'?'));
$types=str_repeat('i',count($ids)+1);
$params=$ids; array_unshift($params,$evento_id);
$sql="SELECT id,nombre,precio,max_por_compra FROM tickets_tipos
      WHERE evento_id=? AND id IN($ph) AND visible=1 AND canal IN('online','todos')";
$st=$conexion->prepare($sql);
$bind=[$types]; foreach($params as $k=>$v){ $bind[]=&$params[$k]; }
call_user_func_array([$st,'bind_param'],$bind);
$st->execute(); $rs=$st->get_result(); $tipos=[];
while($row=$rs->fetch_assoc()){ $tipos[(int)$row['id']]=$row; }
$st->close();

$total=0.0;
foreach($qty as $tid=>$q){
  if(!isset($tipos[$tid])){ $_SESSION['flash_error']='Tipo inválido.'; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }
  if($q > (int)$tipos[$tid]['max_por_compra']){ $_SESSION['flash_error']='Máximo por compra superado en '.$tipos[$tid]['nombre']; header('Location: comprar_entradas.php?evento_id='.$evento_id); exit; }
  $total += (float)$tipos[$tid]['precio']*$q;
}

/* Cobros */
$alias=''; $cuenta='';
if($st=$conexion->prepare("SELECT alias_bancario,titular_banco,banco_nombre FROM eventos_pagos_config WHERE evento_id=?")){
  $st->bind_param('i',$evento_id); $st->execute(); $cfg=$st->get_result()->fetch_assoc()?:[]; $st->close();
  $alias=(string)($cfg['alias_bancario']??''); $cuenta=trim(($cfg['banco_nombre']??'').' - Titular: '.($cfg['titular_banco']??''));
}

/* Comprobante */
$comprobante = save_upload($_FILES['comprobante']??null, $evento_id);

/* Insert PENDIENTE + items */
$conexion->begin_transaction();
try{
  $sql="INSERT INTO pedidos(evento_id,comprador_nombre,comprador_email,comprador_tel,total,origen,metodo_pago,alias_usado,cuenta_destino,comprobante_path,estado)
        VALUES(?,?,?,?,?,'online',?,?,?,?,'pendiente')";
  $st=$conexion->prepare($sql);
  $st->bind_param('isssdssss',$evento_id,$nombre,$email,$tel,$total,$metodo,$alias,$cuenta,$comprobante);
  if(!$st->execute()) throw new Exception('No se pudo crear el pedido. '.$conexion->error);
  $pedido_id=(int)$st->insert_id; $st->close();

  $sqlI="INSERT INTO pedidos_items(pedido_id,tipo_id,cantidad,precio_unit) VALUES(?,?,?,?)";
  $sti=$conexion->prepare($sqlI);
  foreach($qty as $tid=>$q){
    $pu=(float)$tipos[$tid]['precio'];
    $sti->bind_param('iiid',$pedido_id,$tid,$q,$pu);
    if(!$sti->execute()) throw new Exception('No se pudieron guardar los ítems.');
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
