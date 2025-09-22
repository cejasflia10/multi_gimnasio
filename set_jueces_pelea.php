<?php
// set_jueces_pelea.php — guarda EXACTAMENTE 3 jueces habilitados por pelea
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'BD']); exit; }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$pelea_id = isset($_POST['pelea_id']) && is_numeric($_POST['pelea_id']) ? (int)$_POST['pelea_id'] : 0;
$j = isset($_POST['jueces']) ? trim((string)$_POST['jueces']) : '';

if ($pelea_id<=0 || $j===''){ echo json_encode(['ok'=>false,'error'=>'params']); exit; }

$ids = array_values(array_unique(array_filter(array_map(function($x){ $v=(int)trim($x); return $v>0?$v:null; }, explode(',', $j)))));
if (count($ids)!==3) { echo json_encode(['ok'=>false,'error'=>'Necesitás exactamente 3 IDs']); exit; }

// Asegurar tabla
$conexion->query("CREATE TABLE IF NOT EXISTS `jueces_por_pelea` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pelea_id` INT NOT NULL,
  `juez_id` INT NOT NULL,
  `habilitado` TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_pelea_juez` (`pelea_id`,`juez_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Limpiar y grabar 3 filas habilitadas
if ($st=$conexion->prepare("DELETE FROM jueces_por_pelea WHERE pelea_id=?")){
  $st->bind_param('i',$pelea_id); $st->execute(); $st->close();
}
if ($st=$conexion->prepare("INSERT INTO jueces_por_pelea (pelea_id,juez_id,habilitado) VALUES (?,?,1),(?,?,1),(?,?,1)")){
  $st->bind_param('iiiiii', $pelea_id,$ids[0], $pelea_id,$ids[1], $pelea_id,$ids[2]);
  if ($st->execute()){
    $st->close();
    echo json_encode(['ok'=>true,'pelea_id'=>$pelea_id,'habilitados'=>$ids]);
    exit;
  }
  $st->close();
}
echo json_encode(['ok'=>false,'error'=>'save']);
