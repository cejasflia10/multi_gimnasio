<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$gimnasio_id = $_SESSION['gimnasio_id'];

if (!isset($_GET['id'])) {
    die("ID de turno no especificado.");
}

$id = intval($_GET['id']);

// Verificamos que el turno pertenezca al gimnasio del usuario
$verificar = $conexion->prepare("SELECT id FROM turnos WHERE id = ? AND gimnasio_id = ?");
$verificar->bind_param("ii", $id, $gimnasio_id);
$verificar->execute();
$verificar->store_result();

if ($verificar->num_rows === 0) {
    die("Acceso no autorizado o turno inexistente.");
}

// Eliminar turno
$eliminar = $conexion->prepare("DELETE FROM turnos WHERE id = ?");
$eliminar->bind_param("i", $id);
$eliminar->execute();

header("Location: ver_turnos.php");
exit;
?>
