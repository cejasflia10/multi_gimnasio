<?php
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');
@$conexion->set_charset('utf8mb4');

$evento = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
$actual = isset($_GET['actual']) ? (int)$_GET['actual'] : 0;

if ($evento <= 0) { echo json_encode(['ok'=>false]); exit; }

$next_id = null;
if ($r = $conexion->query("SELECT id FROM peleas_evento WHERE evento_id=$evento AND id>$actual AND (resultado IS NULL OR TRIM(resultado)='') ORDER BY orden ASC LIMIT 1")) {
  if ($r->num_rows) $next_id = (int)$r->fetch_assoc()['id'];
}
if (!$next_id && $r2 = $conexion->query("SELECT id FROM peleas_evento WHERE evento_id=$evento ORDER BY orden DESC LIMIT 1")) {
  if ($r2->num_rows) $next_id = (int)$r2->fetch_assoc()['id'];
}

echo json_encode(['ok'=>true,'next_id'=>$next_id]);
