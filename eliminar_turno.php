<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = $_GET['id'] ?? 0;

$conexion->query("DELETE FROM turnos_disponibles WHERE id = $id AND gimnasio_id = $gimnasio_id");
header("Location: cargar_turno.php?eliminado=1");
exit;
