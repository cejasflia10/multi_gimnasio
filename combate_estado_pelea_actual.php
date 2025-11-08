<?php
// combate_estado_pelea_actual.php — helper liviano
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
header('Content-Type: application/json; charset=utf-8');
$evento_id = (int)($_GET['evento_id'] ?? 0);
$pelea_id = 0;
if ($evento_id>0 && $conexion instanceof mysqli){
  if ($st=$conexion->prepare("SELECT pelea_actual_id FROM combate_estado WHERE evento_id=? LIMIT 1")){
    $st->bind_param('i',$evento_id); $st->execute(); $st->bind_result($pid);
    if ($st->fetch()) $pelea_id = (int)$pid; $st->close();
  }
}
echo json_encode(['ok'=>true,'pelea_id'=>$pelea_id]);
