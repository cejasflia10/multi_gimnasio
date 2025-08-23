<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/conexion.php';

$gimnasio_id = (int)($_POST['gimnasio_id'] ?? ($_SESSION['gimnasio_id'] ?? 0));
$tipo_id     = (int)($_POST['tipo_id'] ?? 0);
$fecha       = $_POST['fecha'] ?? date('Y-m-d');
$descripcion = trim($_POST['descripcion'] ?? '');
$monto       = (float)($_POST['monto'] ?? 0);

if ($gimnasio_id <= 0 || $tipo_id <= 0 || $monto <= 0) {
  http_response_code(400); die('Datos inválidos');
}

$stmt = $conexion->prepare("INSERT INTO gastos (gimnasio_id, tipo_id, fecha, descripcion, monto) VALUES (?,?,?,?,?)");
if (!$stmt) { http_response_code(500); die('Prepare: '.$conexion->error); }
$stmt->bind_param('iissd', $gimnasio_id, $tipo_id, $fecha, $descripcion, $monto);
if (!$stmt->execute()) { http_response_code(500); die('Execute: '.$stmt->error); }
$stmt->close();

header('Location: gastos.php?ok=1&ym='.urlencode(substr($fecha,0,7)));
exit;
