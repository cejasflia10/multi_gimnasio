<?php
// estado_votacion.php — devuelve si la votación (descanso) está abierta para una pelea + round
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

header('Content-Type: application/json; charset=utf-8');
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); echo json_encode(['ok'=>false,'err'=>'Sin conexión BD']); exit; }
@$conexion->set_charset('utf8mb4');

$pelea_id = isset($_GET['pelea_id']) && ctype_digit($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id<=0) { echo json_encode(['ok'=>false,'err'=>'pelea_id']); exit; }

// Tabla de estado (si no existe, crear)
$conexion->query("CREATE TABLE IF NOT EXISTS `pelea_votacion` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pelea_id` INT NOT NULL UNIQUE,
  `round_actual` INT NOT NULL DEFAULT 1,
  `votacion_abierta` TINYINT(1) NOT NULL DEFAULT 0,
  `cierra_ts` INT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Leer estado
$st = $conexion->prepare("SELECT round_actual, votacion_abierta, cierra_ts FROM pelea_votacion WHERE pelea_id=? LIMIT 1");
$st->bind_param('i',$pelea_id);
$st->execute();
$st->bind_result($round_actual,$vab,$cierra_ts);
$exists = $st->fetch();
$st->close();

if (!$exists) {
  // default cerrado
  $round_actual = 1; $vab = 0; $cierra_ts = null;
}

// Contar jueces asignados (mínimo 3 requerido)
// Intento 1: get_jueces_pelea.php ya lo usa tu app; si no, probamos tablas comunes
$jueces_asignados = 0;
$posibles = [
  "SELECT COUNT(*) FROM asignaciones_jueces WHERE pelea_id=? AND estado IN ('asignado','activo')",
  "SELECT COUNT(DISTINCT juez_id) FROM resultados_jueces WHERE pelea_id=?", // fallback
];
foreach($posibles as $q){
  if ($st = $conexion->prepare($q)) {
    $st->bind_param('i',$pelea_id); $st->execute(); $st->bind_result($cnt);
    if ($st->fetch()) { $jueces_asignados = max($jueces_asignados,(int)$cnt); }
    $st->close();
  }
}

$min_jueces = 3;
$now = time();
$abierta_por_tiempo = ($vab==1) && ($cierra_ts===null || $now <= (int)$cierra_ts);

echo json_encode([
  'ok'=>true,
  'pelea_id'=>$pelea_id,
  'round_actual'=>(int)$round_actual,
  'votacion_abierta'=> (bool)$abierta_por_tiempo,
  'cierra_ts'=> $cierra_ts ? (int)$cierra_ts : null,
  'jueces_asignados'=>$jueces_asignados,
  'min_jueces'=>$min_jueces,
  'can_vote'=> $abierta_por_tiempo && ($jueces_asignados >= $min_jueces)
]);
