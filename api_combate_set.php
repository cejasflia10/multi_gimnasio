<?php
/* ==========================================================
   api_combate_set.php — Actualiza estado del cronómetro
   Método: POST (o GET para pruebas)
   Campos mínimos:
     - evento_id (int)
     - pelea_id  (int) -> guarda en pelea_actual_id
     - ronda, running, paused, duracion, descanso, remaining, activo
   Devuelve: {ok:true}
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');
function out($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$evento_id = (int)($_POST['evento_id'] ?? $_GET['evento_id'] ?? 0);
$pelea_id  = (int)($_POST['pelea_id']  ?? $_GET['pelea_id']  ?? 0);

if ($evento_id===0 || $pelea_id===0) out(['ok'=>false,'error'=>'evento_id/pelea_id requerido']);

$ronda    = (int)($_POST['ronda']    ?? $_GET['ronda']    ?? 1);
$running  = (int)($_POST['running']  ?? $_GET['running']  ?? 0);
$paused   = (int)($_POST['paused']   ?? $_GET['paused']   ?? 1);
$duracion = (int)($_POST['duracion'] ?? $_GET['duracion'] ?? 180);
$descanso = (int)($_POST['descanso'] ?? $_GET['descanso'] ?? 60);
$remaining= (int)($_POST['remaining']?? $_GET['remaining']?? 180);
$activo   = (int)($_POST['activo']   ?? $_GET['activo']   ?? 1);

/* Upsert simple: insert una fila por evento, o actualiza la última */
$ok = $conexion->query(sprintf(
  "INSERT INTO combate_estado (evento_id, pelea_actual_id, ronda, running, paused, duracion, descanso, remaining, activo, actualizado_en)
   VALUES (%d,%d,%d,%d,%d,%d,%d,%d,%d, NOW())
   ON DUPLICATE KEY UPDATE
     pelea_actual_id=VALUES(pelea_actual_id),
     ronda=VALUES(ronda),
     running=VALUES(running),
     paused=VALUES(paused),
     duracion=VALUES(duracion),
     descanso=VALUES(descanso),
     remaining=VALUES(remaining),
     activo=VALUES(activo),
     actualizado_en=NOW()",
   $evento_id,$pelea_id,$ronda,$running,$paused,$duracion,$descanso,$remaining,$activo
));

if (!$ok) {
  // Si no hay UNIQUE en evento_id, hacemos UPDATE por evento_id (última fila)
  $ok = $conexion->query("
    UPDATE combate_estado
    SET pelea_actual_id={$pelea_id}, ronda={$ronda}, running={$running}, paused={$paused},
        duracion={$duracion}, descanso={$descanso}, remaining={$remaining}, activo={$activo},
        actualizado_en=NOW()
    WHERE evento_id={$evento_id}
    ORDER BY id DESC
    LIMIT 1
  ");
  if ($conexion->affected_rows===0) {
    $ok = $conexion->query("
      INSERT INTO combate_estado (evento_id, pelea_actual_id, ronda, running, paused, duracion, descanso, remaining, activo, actualizado_en)
      VALUES ({$evento_id}, {$pelea_id}, {$ronda}, {$running}, {$paused}, {$duracion}, {$descanso}, {$remaining}, {$activo}, NOW())
    ");
  }
}

out(['ok'=> (bool)$ok]);
