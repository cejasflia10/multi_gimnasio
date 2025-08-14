<?php
// api/accesos/registrar.php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../conexion.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$gimnasio_id = (int)($data['gimnasio_id'] ?? 0);
$persona_tipo = $data['persona_tipo'] ?? 'profesor';
$persona_id = (int)($data['persona_id'] ?? 0);
$dispositivo = $data['dispositivo'] ?? 'ZK4500-1';
$evento = $data['evento'] ?? 'entrada'; // entrada|salida

if (!$gimnasio_id || !$persona_id) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'faltan_campos']); exit;
}

$conexion->query("
  CREATE TABLE IF NOT EXISTS accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gimnasio_id INT NOT NULL,
    persona_tipo ENUM('profesor','cliente') NOT NULL,
    persona_id INT NOT NULL,
    dispositivo VARCHAR(64) NOT NULL,
    evento ENUM('entrada','salida') NOT NULL,
    ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_gym_ts (gimnasio_id, ts)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$stmt = $conexion->prepare("INSERT INTO accesos (gimnasio_id, persona_tipo, persona_id, dispositivo, evento) VALUES (?,?,?,?,?)");
$stmt->bind_param('isiss', $gimnasio_id, $persona_tipo, $persona_id, $dispositivo, $evento);
$stmt->execute();

echo json_encode(['ok'=>true]);
