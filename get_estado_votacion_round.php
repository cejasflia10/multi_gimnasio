<?php
// get_estado_votacion_round.php — stub simple (ajustá a tu lógica real)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$pelea_id = isset($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
// marcá open=true en descanso si querés permitir puntuar; acá lo dejamos abierto:
echo json_encode(['ok'=>true,'open'=>true,'round'=>1]);
