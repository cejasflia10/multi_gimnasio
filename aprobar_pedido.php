<?php
if (session_status()===PHP_SESSION_NONE) session_start();
if (empty($_SESSION['evento_usuario_id'])) {
  http_response_code(403);
  exit('Acceso restringido.');
}

require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function ensure_dir($p){ if(!is_dir($p)) @mkdir($p,0775,true); }
function base_url(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $path   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
  return $scheme.'://'.$host.$path;
}
function code_gen($len=12){
  $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $n=strlen($a); $s='';
  for($i=0;$i<$len;$i++) $s.=$a[random_int(0,$n-1)];
  return $s;
}

$pedido_id = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : (int)($_POST['pedido_id'] ?? 0);
if ($pedido_id<=0){ http_response_code(400); exit('Falta pedido_id'); }

/* Cargar pedido + items */
$sqlP="SELECT id, evento_id, estado FROM pedidos WHERE id=? LIMIT 1";
$st=$conexion->prepare($sqlP); $st->bind_param('i',$pedido_id); $st->execute();
$pedido=$st->get_result()->fetch_assoc(); $st->close();
if(!$pedido){ http_response_code(404); exit('Pedido no encontrado'); }
if(in_array($pedido['estado'],['aprobado','pagado'],true)){
  $_SESSION['ok_msg']='El pedido ya estaba aprobado.';
  header('Location: compra_ok.php?pedido_id='.$pedido_id); exit;
}

$sqlI="SELECT tipo_id, cantidad FROM pedidos_items WHERE pedido_id=?";
$sti=$conexion->prepare($sqlI); $sti->bind_param('i',$pedido_id); $sti->execute();
$items=$sti->get_result()->fetch_all(MYSQLI_ASSOC); $sti->close();
if(!$items){ $_SESSION['flash_error']='Pedido sin ítems.'; header('Location: compra_ok.php?pedido_id='.$pedido_id); exit; }

$evento_id=(int)$pedido['evento_id'];
$conexion->begin_transaction();
try{
  // Validar stock y descontar
  foreach($items as $it){
    $tid=(int)$it['tipo_id']; $q=(int)$it['cantidad'];
    if($q<=0) continue;
    // Verificar disponibilidad
    $chk=$conexion->prepare("SELECT stock_disponible,nombre FROM tickets_tipos WHERE id=? AND evento_id=? FOR UPDATE");
    $chk->bind_param('ii',$tid,$evento_id); $chk->execute();
    $r=$chk->get_result()->fetch_assoc(); $chk->close();
    if(!$r) throw new Exception('Tipo inválido');
    if((int)$r['stock_disponible'] < $q) throw new Exception('Stock insuficiente en '.$r['nombre']);

    $up=$conexion->prepare("UPDATE tickets_tipos SET stock_disponible=stock_disponible-? WHERE id=? AND evento_id=?");
    $up->bind_param('iii',$q,$tid,$evento_id); $up->execute(); $up->close();
  }

  // Emitir tickets + QR
  $dir=__DIR__.'/tickets_qr/evento_'.$evento_id; ensure_dir($dir);
  foreach($items as $it){
    $tid=(int)$it['tipo_id']; $q=(int)$it['cantidad'];
    for($i=0;$i<$q;$i++){
      do{
        $code=code_gen(12);
        $chk=$conexion->prepare("SELECT 1 FROM tickets WHERE code=?");
        $chk->bind_param('s',$code); $chk->execute();
        $ex=$chk->get_result()->num_rows>0; $chk->close();
      }while($ex);

      $ins=$conexion->prepare("INSERT INTO tickets(pedido_id,evento_id,tipo_id,code) VALUES(?,?,?,?)");
      $ins->bind_param('iiis',$pedido_id,$evento_id,$tid,$code);
      $ins->execute(); $ins->close();

      // Generar QR PNG en disco (usando tu qr.php)
      $qrData = base_url().'/mi_entrada.php?code='.$code;
      $rel    = 'tickets_qr/evento_'.$evento_id.'/'.$code.'.png';
      @file_get_contents(base_url().'/qr.php?text='.urlencode($qrData).'&save='.urlencode($rel));

      // Guardar path si se generó
      if (is_file(__DIR__.'/'.$rel)) {
        $up=$conexion->prepare("UPDATE tickets SET qr_path=? WHERE code=?");
        $up->bind_param('ss',$rel,$code); $up->execute(); $up->close();
      }
    }
  }

  // Aprobar pedido
  $ap=$conexion->prepare("UPDATE pedidos SET estado='aprobado' WHERE id=?");
  $ap->bind_param('i',$pedido_id); $ap->execute(); $ap->close();

  $conexion->commit();
  $_SESSION['ok_msg']='Pedido aprobado y entradas emitidas.';
  header('Location: compra_ok.php?pedido_id='.$pedido_id); exit;

}catch(Exception $e){
  $conexion->rollback();
  $_SESSION['flash_error']='Error: '.$e->getMessage();
  header('Location: compra_ok.php?pedido_id='.$pedido_id); exit;
}
