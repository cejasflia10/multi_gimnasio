<?php
include 'conexion.php';
session_start();

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$turno_id = $_POST['turno_id'] ?? 0;
$fecha = $_POST['fecha'] ?? date('Y-m-d');

if ($cliente_id && $turno_id && $fecha) {
    $conexion->query("DELETE FROM reservas WHERE cliente_id = $cliente_id AND turno_id = $turno_id AND fecha = '$fecha'");
}

header("Location: reservar_turno.php?dia=" . date('N', strtotime($fecha)));
exit;
