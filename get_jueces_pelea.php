<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($conexion) || !($conexion instanceof mysqli)) { echo json_encode(['ok'=>false,'error'=>'no_db']); exit; }
@$conexion->set_charset('utf8mb4');

$pelea_id = isset($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id<=0){ echo json_encode(['ok'=>false,'error'=>'pelea_id']); exit; }

$habilitados=[];
$haveJPP = ($rs=$conexion->query("SHOW TABLES LIKE 'jueces_por_pelea'")) && $rs->num_rows>0; if($rs) $rs->close();
if ($haveJPP){
  if ($st=$conexion->prepare("SELECT juez_id FROM jueces_por_pelea WHERE pelea_id=? AND habilitado=1 ORDER BY juez_id")){
    $st->bind_param('i',$pelea_id); $st->execute(); $r=$st->get_result();
    while($row=$r->fetch_assoc()) $habilitados[]=(int)$row['juez_id'];
    $st->close();
  }
}
if (count($habilitados)!==3){
  $habilitados=[];
  if ($st=$conexion->prepare("SELECT DISTINCT juez_id FROM puntuaciones_jueces WHERE pelea_id=? ORDER BY juez_id LIMIT 3")){
    $st->bind_param('i',$pelea_id); $st->execute(); $r=$st->get_result();
    while($row=$r->fetch_assoc()) $habilitados[]=(int)$row['juez_id'];
    $st->close();
  }
}

$jueces=[];
$tbl=null;
if (($rs=$conexion->query("SHOW TABLES LIKE 'jueces_evento'")) && $rs->num_rows>0) $tbl='jueces_evento';
elseif (($rs=$conexion->query("SHOW TABLES LIKE 'jueces'")) && $rs->num_rows>0) $tbl='jueces';
if ($tbl){
  $qq=$conexion->query("SELECT id, CONCAT(TRIM(COALESCE(apellido,'')),' ',TRIM(COALESCE(nombre,''))) nom FROM `$tbl` ORDER BY id");
  while($rw=$qq->fetch_assoc()){ $jueces[]=['id'=>(int)$rw['id'],'nombre'=>trim($rw['nom']?:('Juez '.$rw['id']))]; }
}

echo json_encode(['ok'=>true,'jueces'=>$jueces,'habilitados'=>$habilitados]);
