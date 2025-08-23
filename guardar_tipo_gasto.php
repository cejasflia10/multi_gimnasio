<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/conexion.php';
header('Content-Type: application/json');

$gimnasio_id = (int)($_POST['gimnasio_id'] ?? ($_SESSION['gimnasio_id'] ?? 0));
$nombre = trim($_POST['nombre'] ?? '');
if ($gimnasio_id<=0 || $nombre===''){ echo json_encode(['ok'=>false]); exit; }

$stmt = $conexion->prepare("INSERT INTO gastos_tipos (gimnasio_id, nombre) VALUES (?, ?)");
if (!$stmt){ echo json_encode(['ok'=>false]); exit; }
$stmt->bind_param('is', $gimnasio_id, $nombre);
$ok = $stmt->execute();
$id = $stmt->insert_id;
$stmt->close();

echo json_encode(['ok'=>$ok, 'id'=>$id]);
