<?php
// api_combate_estado.php — Publicador de estado/timer para overlay
// Recibe POST desde combate_en_vivo.js (publishTimer/marcar/pausar) y guarda en combate_estado.
//
// Campos esperados (POST):
//   evento_id (int)          — requerido
//   pelea_actual_id (int)    — opcional (id de pelea en juego)
//   activo (0|1)             — opcional (1 si el timer está en marcha)
//   ronda_actual (int)       — opcional
//   en_descanso (0|1)        — opcional
//   epoch_inicio (int, unix) — opcional (inicio de la fase actual round/descanso)
//   dur_round (int)          — opcional (segundos)
//   dur_descanso (int)       — opcional (segundos)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db']);
  exit;
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function gi($k,$def=null){ if(!isset($_POST[$k])) return $def; $v=trim((string)$_POST[$k]); return ($v===''? $def : (int)$v); }

// Asegurar tabla
$conexion->query("
  CREATE TABLE IF NOT EXISTS combate_estado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL UNIQUE,
    pelea_actual_id INT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    ronda_actual INT DEFAULT 1,
    en_descanso TINYINT(1) NOT NULL DEFAULT 0,
    epoch_inicio INT DEFAULT NULL,
    dur_round INT DEFAULT 180,
    dur_descanso INT DEFAULT 60,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$evento_id      = gi('evento_id', 0);
if ($evento_id <= 0) { echo json_encode(['ok'=>false,'error'=>'evento_id']); exit; }

$pelea_actual_id= gi('pelea_actual_id', null);
$activo         = gi('activo', null);
$ronda_actual   = gi('ronda_actual', null);
$en_descanso    = gi('en_descanso', null);
$epoch_inicio   = gi('epoch_inicio', null);
$dur_round      = gi('dur_round', null);
$dur_descanso   = gi('dur_descanso', null);

// Build dinámico
$cols = ['evento_id']; $ph=['?']; $types='i'; $vals=[ $evento_id ];

$upd = [];

if (!is_null($pelea_actual_id)){ $cols[]='pelea_actual_id'; $ph[]='?'; $types.='i'; $vals[]=$pelea_actual_id; $upd[]='pelea_actual_id=VALUES(pelea_actual_id)'; }
if (!is_null($activo))        { $cols[]='activo';         $ph[]='?'; $types.='i'; $vals[]=$activo?1:0;       $upd[]='activo=VALUES(activo)'; }
if (!is_null($ronda_actual))  { $cols[]='ronda_actual';   $ph[]='?'; $types.='i'; $vals[]=$ronda_actual;      $upd[]='ronda_actual=VALUES(ronda_actual)'; }
if (!is_null($en_descanso))   { $cols[]='en_descanso';    $ph[]='?'; $types.='i'; $vals[]=$en_descanso?1:0;   $upd[]='en_descanso=VALUES(en_descanso)'; }
if (!is_null($epoch_inicio))  { $cols[]='epoch_inicio';   $ph[]='?'; $types.='i'; $vals[]=$epoch_inicio;      $upd[]='epoch_inicio=VALUES(epoch_inicio)'; }
if (!is_null($dur_round))     { $cols[]='dur_round';      $ph[]='?'; $types.='i'; $vals[]=$dur_round;         $upd[]='dur_round=VALUES(dur_round)'; }
if (!is_null($dur_descanso))  { $cols[]='dur_descanso';   $ph[]='?'; $types.='i'; $vals[]=$dur_descanso;      $upd[]='dur_descanso=VALUES(dur_descanso)'; }

if (count($cols)===1){
  // Solo vino evento_id y nada para actualizar
  echo json_encode(['ok'=>true,'noop'=>1]); exit;
}

$sql = "INSERT INTO combate_estado (".implode(',',$cols).")
        VALUES (".implode(',',$ph).")
        ON DUPLICATE KEY UPDATE ".implode(',', $upd).", actualizado_en=CURRENT_TIMESTAMP";

if ($st=$conexion->prepare($sql)){
  $st->bind_param($types, ...$vals);
  $st->execute();
  $st->close();
  echo json_encode(['ok'=>true]);
} else {
  echo json_encode(['ok'=>false,'error'=>'prepare']);
}
