<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$turno_id = $_POST['turno_id'] ?? 0;

$conexion->query("DELETE FROM reservas WHERE cliente_id = $cliente_id AND turno_id = $turno_id");

header("Location: reservar_turno_cliente.php");
exit;
?>
