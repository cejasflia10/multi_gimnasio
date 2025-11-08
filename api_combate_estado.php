<?php
/* ============================================================
   api_combate_estado.php — Devuelve el estado para el overlay
   GET:
     - pelea_id (preferente), o
     - evento_id (seguirá overlay_now.evento_id → pelea_id)
   Respuesta:
   {
     "ok": true,
     "pelea_id": 32,
     "estado": { ... JSON guardado ... }
   }
============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Sin BD']); exit; }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function jfail($msg,$code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
function geti($k){ return isset($_GET[$k]) && preg_match('/^\d+$/', (string)$_GET[$k]) ? (int)$_GET[$k] : 0; }

$pelea_id  = geti('pelea_id');
$evento_id = geti('evento_id');

if ($pelea_id <= 0 && $evento_id > 0) {
  // Buscar pelea actual por evento
  $sql = "SELECT pelea_id FROM overlay_now WHERE evento_id=? LIMIT 1";
  if ($st = $conexion->prepare($sql)) {
    $st->bind_param('i', $evento_id);
    $st->execute();
    $st->bind_result($pid);
    if ($st->fetch()) $pelea_id = (int)$pid;
    $st->close();
  }
}

if ($pelea_id <= 0) jfail('pelea_id o evento_id requeridos');

$estado = null;
$sql2   = "SELECT estado_json FROM combate_estado_live WHERE pelea_id=? LIMIT 1";
if ($st2 = $conexion->prepare($sql2)) {
  $st2->bind_param('i', $pelea_id);
  $st2->execute();
  $st2->bind_result($j);
  if ($st2->fetch()) {
    $estado = json_decode($j, true);
  }
  $st2->close();
}

if (!$estado) {
  // Si aún no hay estado, devolvemos uno “LISTO” por defecto
  $estado = [
    'ronda'        => 1,
    'dur_round'    => 180,
    'remaining'    => 180,
    'paused'       => true,
    'en_descanso'  => false,
    'activo'       => false,
    'epoch_inicio' => 0,
    'ronda_actual' => 1,
    'estado'       => 'listo'
  ];
}

echo json_encode(['ok'=>true,'pelea_id'=>$pelea_id,'estado'=>$estado], JSON_UNESCAPED_UNICODE);
