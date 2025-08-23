<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

$id          = (int)($_POST['id'] ?? 0);
$gimnasio_id = (int)($_POST['gimnasio_id'] ?? ($_SESSION['gimnasio_id'] ?? 0));
$clases      = isset($_POST['clases_disponibles']) ? (int)$_POST['clases_disponibles'] : null;
$venc        = $_POST['fecha_vencimiento'] ?? null;

if ($id <= 0 || $gimnasio_id <= 0) { http_response_code(400); die('Datos inválidos'); }
if ($clases === null || $venc === null) { http_response_code(400); die('Faltan campos'); }

// Validación simple de fecha (formato YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc)) { http_response_code(400); die('Fecha inválida'); }

try {
  $stmt = $conexion->prepare("
    UPDATE membresias
       SET clases_disponibles = ?, fecha_vencimiento = ?
     WHERE id = ? AND gimnasio_id = ?
    LIMIT 1
  ");
  if (!$stmt) { throw new Exception('Prepare: '.$conexion->error); }
  $stmt->bind_param('isii', $clases, $venc, $id, $gimnasio_id);
  if (!$stmt->execute()) { throw new Exception('Execute: '.$stmt->error); }
  $stmt->close();

  header('Location: ver_membresias.php?edit_ok=1');
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo "Error al editar membresía: ".$e->getMessage();
}
