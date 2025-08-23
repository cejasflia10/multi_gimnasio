<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require __DIR__.'/conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
if ($gimnasio_id<=0 || $id<=0){ http_response_code(400); die('Datos inválidos'); }

$stmt = $conexion->prepare("DELETE FROM gastos WHERE id = ? AND gimnasio_id = ? LIMIT 1");
if (!$stmt){ http_response_code(500); die('Prepare: '.$conexion->error); }
$stmt->bind_param('ii', $id, $gimnasio_id);
$stmt->execute();
$stmt->close();

$ym = $_GET['ym'] ?? date('Y-m');
header('Location: gastos.php?del=1&ym='.urlencode($ym));
exit;
