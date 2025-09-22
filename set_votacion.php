<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache');

$out=['ok'=>false];
if(!isset($conexion)||!($conexion instanceof mysqli)){ echo json_encode($out); exit; }
@$conexion->set_charset('utf8mb4');

$pelea_id=isset($_POST['pelea_id'])?(int)$_POST['pelea_id']:0;
$action=strtolower(trim((string)($_POST['action']??'')));
$round=isset($_POST['round'])?(int)$_POST['round']:1;
$seconds=isset($_POST['seconds'])?(int)$_POST['seconds']:0;
if($pelea_id<=0 || !in_array($action,['open','close'],true)){ echo json_encode($out); exit; }

$conexion->query("CREATE TABLE IF NOT EXISTS combate_vivo_estado(
  pelea_id INT PRIMARY KEY, `open` TINYINT(1) NOT NULL DEFAULT 0, round_actual INT NOT NULL DEFAULT 1,
  expires_at TIMESTAMP NULL DEFAULT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_expires(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if($action==='open'){
  if($seconds>0){
    $sql="INSERT INTO combate_vivo_estado(pelea_id,`open`,round_actual,expires_at)
          VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL ? SECOND))
          ON DUPLICATE KEY UPDATE `open`=VALUES(`open`),round_actual=VALUES(round_actual),expires_at=VALUES(expires_at)";
    if($st=$conexion->prepare($sql)){ $one=1; $st->bind_param('iiii',$pelea_id,$one,$round,$seconds); $ok=$st->execute(); $st->close(); echo json_encode(['ok'=>$ok]); exit; }
  }else{
    $sql="INSERT INTO combate_vivo_estado(pelea_id,`open`,round_actual,expires_at)
          VALUES (?,?,?,NULL)
          ON DUPLICATE KEY UPDATE `open`=VALUES(`open`),round_actual=VALUES(round_actual),expires_at=NULL";
    if($st=$conexion->prepare($sql)){ $one=1; $st->bind_param('iii',$pelea_id,$one,$round); $ok=$st->execute(); $st->close(); echo json_encode(['ok'=>$ok]); exit; }
  }
}
if($action==='close'){
  $sql="INSERT INTO combate_vivo_estado(pelea_id,`open`,round_actual,expires_at)
        VALUES (?,0,?,NULL)
        ON DUPLICATE KEY UPDATE `open`=0,round_actual=VALUES(round_actual),expires_at=NULL";
  if($st=$conexion->prepare($sql)){ $st->bind_param('ii',$pelea_id,$round); $ok=$st->execute(); $st->close(); echo json_encode(['ok'=>$ok]); exit; }
}
echo json_encode($out);
