<?php
// api/biometria/enrolar.php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../conexion.php';

header('Content-Type: application/json; charset=utf-8');

// (opcional) validar token simple
$token = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($token !== getenv('API_KEY_BIOMETRIA')) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$persona_tipo = $data['persona_tipo'] ?? '';    // 'profesor' | 'cliente'
$persona_id   = (int)($data['persona_id'] ?? 0);
$gimnasio_id  = (int)($data['gimnasio_id'] ?? 0);
$template_b64 = $data['template_b64'] ?? '';
$version      = $data['version'] ?? 'ZKFinger10';

if (!$persona_tipo || !$persona_id || !$gimnasio_id || !$template_b64) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'faltan_campos']); exit;
}

$template = base64_decode($template_b64, true);
if ($template===false) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'template_invalido']); exit;
}

// Usando tabla "huellas" (opción B)
$stmt = $conexion->prepare("
  INSERT INTO huellas (persona_tipo, persona_id, gimnasio_id, template, version, activa)
  VALUES (?, ?, ?, ?, ?, 1)
  ON DUPLICATE KEY UPDATE template=VALUES(template), version=VALUES(version), activa=1, creada_at=NOW()
");
$stmt->bind_param('siiss', $persona_tipo, $persona_id, $gimnasio_id, $template, $version);
$stmt->send_long_data(3, $template);
$stmt->execute();

echo json_encode(['ok'=>true]);
