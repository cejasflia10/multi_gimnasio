<?php
// api_combate_estado_poll.php
// Devuelve el estado actual del combate para un evento_id
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function get_int($k){ return isset($_GET[$k]) ? (int)$_GET[$k] : 0; }

$evento_id = get_int('evento_id');
if ($evento_id <= 0) { echo json_encode(['ok'=>false,'error'=>'evento_id_invalido']); exit; }

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  echo json_encode(['ok'=>false,'error'=>'db']); exit;
}
@$conexion->set_charset('utf8mb4');

// combate_estado tiene: evento_id, pelea_actual_id, activo, actualizado_en
$st = $conexion->prepare("SELECT evento_id, pelea_actual_id, activo, 
                                 IFNULL(ronda_actual, NULL) AS ronda_actual,
                                 IFNULL(en_descanso, NULL) AS en_descanso,
                                 IFNULL(epoch_inicio, NULL) AS epoch_inicio,
                                 IFNULL(dur_round, NULL) AS dur_round,
                                 IFNULL(dur_descanso, NULL) AS dur_descanso,
                                 UNIX_TIMESTAMP(actualizado_en) AS ts
                          FROM combate_estado
                          WHERE evento_id=? LIMIT 1");
if (!$st){ echo json_encode(['ok'=>false,'error'=>'no_table']); exit; }
$st->bind_param('i',$evento_id);
$st->execute();
$r = $st->get_result();
$row = $r->fetch_assoc();
$st->close();

if (!$row){ echo json_encode(['ok'=>true,'data'=>null]); exit; }

echo json_encode(['ok'=>true,'data'=>$row,'now'=>time()]);
