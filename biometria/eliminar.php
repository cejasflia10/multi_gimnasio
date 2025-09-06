<?php
// /api/biometria/eliminar.php
if (session_status()===PHP_SESSION_NONE) session_start();
$root = __DIR__.'/..';
require_once $root.'/../conexion.php';
header('Content-Type: application/json; charset=utf-8');

$API_KEY = getenv('API_KEY_BIOMETRIA') ?: '';
if ($API_KEY) {
  $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
  if (!hash_equals($API_KEY, $key)) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'API key inválida']); exit; }
}

if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido']); exit; }

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);

$persona_tipo = $in['persona_tipo'] ?? '';
$persona_id   = (int)($in['persona_id'] ?? 0);
$gimnasio_id  = (int)($in['gimnasio_id'] ?? 0);

if (!$gimnasio_id || !$persona_id || !in_array($persona_tipo,['profesor','cliente'],true)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Parámetros inválidos']); exit;
}
$session_gym = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($session_gym && $session_gym !== $gimnasio_id) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Gimnasio de sesión no coincide']); exit; }

$st = $conexion->prepare("DELETE FROM huellas WHERE gimnasio_id=? AND persona_tipo=? AND persona_id=?");
$st->bind_param('isi', $gimnasio_id, $persona_tipo, $persona_id);
$st->execute();

echo json_encode(['ok'=>true,'deleted'=>$st->affected_rows]);
