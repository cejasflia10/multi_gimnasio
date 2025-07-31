<?php
session_start();
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID inválido");
}

$profesor_id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($profesor_id > 0 && $gimnasio_id > 0) {
    // Eliminar turnos y reservas
    $conexion->query("DELETE FROM reservas WHERE turno_id IN (SELECT id FROM turnos WHERE profesor_id=$profesor_id AND gimnasio_id=$gimnasio_id)");
    $conexion->query("DELETE FROM turnos WHERE profesor_id=$profesor_id AND gimnasio_id=$gimnasio_id");

    // Eliminar profesor
    $conexion->query("DELETE FROM profesores WHERE id=$profesor_id AND gimnasio_id=$gimnasio_id");
}

header("Location: gestionar_profesores.php?msg=eliminado");
exit;
?>
