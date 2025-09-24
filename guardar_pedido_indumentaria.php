<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/cloudy_boot_constants.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id<=0){ header('Location: login.php'); exit; }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'] ?? '')) {
  http_response_code(400); exit('❌ CSRF');
}
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) { http_response_code(400); exit('❌ Carrito vacío'); }

$modo = $_POST['pago'] ?? 'sena_efectivo';
$CLOUDY = cloudy_constants_init();

/* Recalcular total desde BD */
$total = 0.0;
foreach($cart as $it){
  $pid=(int)$it['producto_id']; $qty=(int)$it['cantidad'];
  $st=$conexion->prepare("SELECT precio FROM ind_productos WHERE id=? AND gimnasio_id=? AND activo=1");
  $st->bind_param('ii',$pid,$gimnasio_id); $st->execute();
  $p=$st->get_result()->fetch_assoc(); $st->close();
  if(!$p) continue;
  $precio=(float)$p['precio'];
  $total += $precio * $qty;
}
$total = round($total,2);

/* Modalidad pago */
$SENA_PORC = 0.30;
$estado_pago='pendiente'; $monto_pagado=0.0; $metodo=NULL;
switch($modo){
  case 'sena_efectivo':       $estado_pago='sena';   $monto_pagado=round($total*$SENA_PORC,2); $metodo='efectivo'; break;
  case 'total_efectivo':      $estado_pago='pagado'; $monto_pagado=$total; $metodo='efectivo'; break;
  case 'sena_transferencia':  $estado_pago='sena';   $monto_pagado=round($total*$SENA_PORC,2); $metodo='transferencia'; break;
  case 'total_transferencia': $estado_pago='pagado'; $monto_pagado=$total; $metodo='transferencia'; break;
}

$comp_url=null; $comp_pid=null;
if (strpos($modo,'transferencia')!==false && isset($_FILES['comprobante']) && $_FILES['comprobante']['error']!==UPLOAD_ERR_NO_FILE){
  if ($_FILES['comprobante']['error']===UPLOAD_ERR_OK && $_FILES['comprobante']['size']<=6*1024*1024 && ($CLOUDY['ok']??false)){
    [$comp_url,$comp_pid] = cloudy_upload($_FILES['comprobante']['tmp_name'], "indumentaria/comprobantes/gym_$gimnasio_id/cli_$cliente_id");
  }
}

$conexion->begin_transaction();
try{
  $st=$conexion->prepare("INSERT INTO ind_pedidos
    (gimnasio_id,cliente_id,total,estado_pago,monto_pagado,metodo_pago,comprobante_url,comprobante_public_id)
    VALUES (?,?,?,?,?,?,?,?)");
  if(!$st){ throw new Exception('SQL pedido: '.$conexion->error); }
  // tipos: i i d s d s s s
  $st->bind_param('iisdssds', $gimnasio_id,$cliente_id,$total,$estado_pago,$monto_pagado,$metodo,$comp_url,$comp_pid);
  if(!$st->execute()){ $err=$st->error; $st->close(); throw new Exception('Exec pedido: '.$err); }
  $pedido_id=$st->insert_id; $st->close();

  // Items (con medidas)
  $sti=$conexion->prepare("INSERT INTO ind_pedido_items
    (pedido_id,producto_id,guia_tipo,talle,talle_sugerido,medidas_json,cantidad,precio_unit,subtotal)
    VALUES (?,?,?,?,?,?,?,?,?)");
  if(!$sti){ throw new Exception('SQL items: '.$conexion->error); }

  foreach($cart as $it){
    $pid     = (int)$it['producto_id'];
    $talle   = (string)$it['talle'];
    $qty     = (int)$it['cantidad'];
    $precio  = (float)$it['precio'];
    $sub     = round($precio*$qty,2);

    $guia    = $it['guia_tipo'] ?? null;
    $sug     = $it['talle_calc'] ?? null;
    $medjson = $it['medidas_json'] ?? null;

    $sti->bind_param('iissssidd', $pedido_id,$pid,$guia,$talle,$sug,$medjson,$qty,$precio,$sub);
    if(!$sti->execute()){ $e=$sti->error; $sti->close(); throw new Exception('Exec item: '.$e); }
  }
  $sti->close();

  $conexion->commit();
  $_SESSION['cart']=[];

  header('Location: mis_pedidos_indumentaria.php?ok=1&id='.$pedido_id);
  exit;

}catch(Throwable $ex){
  $conexion->rollback();
  http_response_code(500);
  echo '❌ Error guardando pedido: '.$ex->getMessage();
}
