<?php
// api/biometria/templates.php
require_once __DIR__ . '/../conexion.php';
header('Content-Type: application/json; charset=utf-8');

$gimnasio_id = (int)($_GET['gimnasio_id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'profesor'; // profesor|cliente
if (!$gimnasio_id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'gimnasio_id']); exit; }

$stmt = $conexion->prepare("SELECT persona_id, version, template FROM huellas WHERE gimnasio_id=? AND persona_tipo=? AND activa=1");
$stmt->bind_param('is', $gimnasio_id, $tipo);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
  $items[] = [
    'persona_id' => (int)$row['persona_id'],
    'version'    => $row['version'],
    'template_b64' => base64_encode($row['template']),
  ];
}
echo json_encode(['ok'=>true, 'templates'=>$items]);
