<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

// Validar sesión
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($gimnasio_id <= 0) {
    die("Acceso denegado.");
}

// Validar ID
$cliente_id = intval($_GET['id'] ?? 0);
if ($cliente_id <= 0) {
    die("ID inválido.");
}

// Marcar como visto
$stmt = $conexion->prepare("UPDATE clientes SET nuevo_online = 0 WHERE id = ? AND gimnasio_id = ?");
$stmt->bind_param("ii", $cliente_id, $gimnasio_id);
$stmt->execute();
$stmt->close();

// Redirigir a la página anterior o al index
if (!empty($_SERVER['HTTP_REFERER'])) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
} else {
    header("Location: index.php");
}
exit;
