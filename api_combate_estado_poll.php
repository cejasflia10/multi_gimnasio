<?php
/* ==========================================================
   api_combate_estado_poll.php — PÚBLICA (solo lectura)
   Devuelve el estado del combate para HUD:
   - pelea_actual_id, activo, ronda_actual, en_descanso
   - epoch_inicio (UNIX), dur_round, dur_descanso

   IMPORTANTE:
   • No requiere login.
   • No usa $_SESSION para lógica de acceso.
   • Asegurate que la "mesa" escriba en ESTA misma base/host.
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'err'=>'BD']); exit;
}

$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id<=0){
  echo json_encode(['ok'=>false,'err'=>'bad_event']); exit;
}

/*  Tabla esperada:
    combate_estado:
      evento_id (PK o UNIQUE),
      pelea_actual_id INT,
      activo TINYINT(1),
      ronda_actual INT,
      en_descanso TINYINT(1),
      epoch_inicio INT,
      dur_round INT,
      dur_descanso INT
*/
$sql = "SELECT pelea_actual_id, activo, ronda_actual, en_descanso, epoch_inicio, dur_round, dur_descanso
        FROM combate_estado
        WHERE evento_id={$evento_id}
        LIMIT 1";
$r = $conexion->query($sql);
$resp = null;
if ($r && $r->num_rows) {
  $resp = $r->fetch_assoc();
  foreach (['pelea_actual_id','activo','ronda_actual','en_descanso','epoch_inicio','dur_round','dur_descanso'] as $k){
    $resp[$k] = is_null($resp[$k]) ? 0 : 0 + $resp[$k];
  }
}
if ($r instanceof mysqli_result) $r->free();

echo json_encode(['ok'=>true,'data'=>$resp], JSON_UNESCAPED_UNICODE);
