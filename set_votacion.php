<?php
// set_votacion.php — abre/cierra la ventana de votación (descanso) desde "Combate en vivo"
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

header('Content-Type: application/json; charset=utf-8');
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); echo json_encode(['ok'=>false,'err'=>'Sin conexión BD']); exit; }
@$conexion->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method!=='POST') { http_response_code(405); echo json_encode(['ok'=>false,'err'=>'POST requerido']); exit; }

$pelea_id = isset($_POST['pelea_id']) && ctype_digit($_POST['pelea_id']) ? (int)$_POST['pelea_id'] : 0;
$accion   = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
$round    = isset($_POST['round']) && ctype_digit($_POST['round']) ? (int)$_POST['round'] : 0;
$seconds  = isset($_POST['seconds']) && ctype_digit($_POST['seconds']) ? (int)$_POST['seconds'] : 60;

if ($pelea_id<=0 || !in_array($accion,['open','close'],true)) {
  echo json_encode(['ok'=>false,'err'=>'params']); exit;
}

// Crear tabla si no existe
$conexion->query("CREATE TABLE IF NOT EXISTS `pelea_votacion` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pelea_id` INT NOT NULL UNIQUE,
  `round_actual` INT NOT NULL DEFAULT 1,
  `votacion_abierta` TINYINT(1) NOT NULL DEFAULT 0,
  `cierra_ts` INT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($accion==='open') {
  if ($round<=0) { echo json_encode(['ok'=>false,'err'=>'round']); exit; }

  // Asegurar que hay ≥ 3 jueces antes de abrir
  $jueces = 0;
  if ($st=$conexion->prepare("SELECT COUNT(*) FROM asignaciones_jueces WHERE pelea_id=? AND estado IN ('asignado','activo')")){
    $st->bind_param('i',$pelea_id); $st->execute(); $st->bind_result($j); if($st->fetch()) $jueces=(int)$j; $st->close();
  }
  if ($jueces < 3) { echo json_encode(['ok'=>false,'err'=>'min_jueces']); exit; }

  $cierra_ts = time() + max(10,$seconds);
  $sql = "INSERT INTO pelea_votacion (pelea_id,round_actual,votacion_abierta,cierra_ts)
          VALUES (?,?,1,?)
          ON DUPLICATE KEY UPDATE round_actual=VALUES(round_actual), votacion_abierta=1, cierra_ts=VALUES(cierra_ts)";
  if ($st=$conexion->prepare($sql)){
    $st->bind_param('iii',$pelea_id,$round,$cierra_ts);
    $ok=$st->execute(); $st->close();
    echo json_encode(['ok'=>$ok,'cierra_ts'=>$cierra_ts,'round_actual'=>$round]); exit;
  }
  echo json_encode(['ok'=>false,'err'=>'db']); exit;
}

if ($accion==='close') {
  $sql = "INSERT INTO pelea_votacion (pelea_id,round_actual,votacion_abierta,cierra_ts)
          VALUES (?,GREATEST(1,COALESCE((SELECT round_actual FROM pelea_votacion pv WHERE pv.pelea_id=?),1)),0,NULL)
          ON DUPLICATE KEY UPDATE votacion_abierta=0, cierra_ts=NULL";
  if ($st=$conexion->prepare($sql)){
    $st->bind_param('ii',$pelea_id,$pelea_id);
    $ok=$st->execute(); $st->close();
    echo json_encode(['ok'=>$ok]); exit;
  }
  echo json_encode(['ok'=>false,'err'=>'db']); exit;
}
