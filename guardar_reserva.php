<?php
include 'conexion.php';
session_start();

$cliente_id = $_POST['cliente_id'] ?? 0;
$turno_id = $_POST['turno_id'] ?? 0;
$fecha = $_POST['fecha'] ?? date('Y-m-d');

if ($cliente_id && $turno_id && $fecha) {
    // Verificar si ya tiene reserva para ese día
    $verifica = $conexion->query("SELECT id FROM reservas WHERE cliente_id = $cliente_id AND fecha = '$fecha'");

    if ($verifica->num_rows > 0) {
        echo "<script>alert('Ya tenés una reserva para ese día');window.history.back();</script>";
        exit;
    }

    $stmt = $conexion->prepare("INSERT INTO reservas (cliente_id, turno_id, fecha) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $cliente_id, $turno_id, $fecha);
    $stmt->execute();
}

header("Location: reservar_turno.php?dia=" . date('N', strtotime($fecha)));
exit;
