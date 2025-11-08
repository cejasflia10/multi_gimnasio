<?php
/* ============================================================
   api_combate_set.php — Guarda el estado en vivo de una pelea
   Body: JSON
   {
     "pelea_id": 32,
     "evento_id": 9,          // opcional (si lo mandás, actualiza overlay_now)
     "estado": {
        "ronda": 1,
        "dur_round": 180,
        "remaining": 152,
        "paused": false,
        "en_descanso": false,
        "activo": true,
        "epoch_inicio": 1731009902,
        "ronda_actual": 1
        // ... cualquier otro dato que quieras mostrar en el overlay
     }
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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) jfail('JSON inválido');

$pelea_id  = isset($data['pelea_id']) ? (int)$data['pelea_id'] : 0;
$estado    = isset($data['estado']) && is_array($data['estado']) ? $data['estado'] : null;
$evento_id = isset($data['evento_id']) ? (int)$data['evento_id'] : 0;

if ($pelea_id <= 0) jfail('pelea_id requerido');
if (!$estado) jfail('estado requerido (objeto)');

// Normalizamos algunas banderas para el overlay
$estado_norm = [
  'ronda'        => (int)($estado['ronda'] ?? $estado['ronda_actual'] ?? 1),
  'dur_round'    => (int)($estado['dur_round'] ?? $estado['duracion'] ?? 180),
  'remaining'    => (int)($estado['remaining'] ?? 0),
  'paused'       => (bool)($estado['paused'] ?? false),
  'en_descanso'  => (bool)($estado['en_descanso'] ?? false),
  'activo'       => (bool)($estado['activo'] ?? true),
  'epoch_inicio' => (int)($estado['epoch_inicio'] ?? 0),
  // extras opcionales que quieras mostrar:
  'ronda_actual' => (int)($estado['ronda_actual'] ?? 1),
  'estado'       => (string)($estado['estado'] ?? ''), // "pausado", "descanso", etc.
];

// Upsert del estado de la pelea
$json = json_encode($estado_norm, JSON_UNESCAPED_UNICODE);
$sql  = "INSERT INTO combate_estado_live (pelea_id, estado_json, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE estado_json=VALUES(estado_json), updated_at=NOW()";
if ($st = $conexion->prepare($sql)) {
  $st->bind_param('is', $pelea_id, $json);
  $ok = $st->execute();
  $st->close();
  if (!$ok) jfail('No se pudo guardar estado (DB)');
} else {
  jfail('Prepare falló');
}

// Si viene evento_id, seteamos pelea actual (overlay seguirá esto)
if ($evento_id > 0) {
  $sql2 = "INSERT INTO overlay_now (evento_id, pelea_id, updated_at)
           VALUES (?, ?, NOW())
           ON DUPLICATE KEY UPDATE pelea_id=VALUES(pelea_id), updated_at=NOW()";
  if ($st2 = $conexion->prepare($sql2)) {
    $st2->bind_param('ii', $evento_id, $pelea_id);
    $st2->execute();
    $st2->close();
  }
}

echo json_encode(['ok'=>true,'pelea_id'=>$pelea_id,'evento_id'=>$evento_id,'estado'=>$estado_norm], JSON_UNESCAPED_UNICODE);
