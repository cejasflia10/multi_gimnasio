<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$turno_id = $_POST['turno_id'] ?? 0;
$cliente_id = $_POST['cliente_id'] ?? 0;
$hoy = date('Y-m-d');

// Obtener el id_dia del turno
$turno = $conexion->query("SELECT id_dia FROM turnos WHERE id = $turno_id")->fetch_assoc();
$id_dia = $turno['id_dia'] ?? 0;

// Verificar si ya tiene una reserva para ese día
$verificar = $conexion->query("
    SELECT * FROM reservas 
    WHERE cliente_id = $cliente_id 
    AND fecha = '$hoy' 
    AND turno_id IN (
        SELECT id FROM turnos WHERE id_dia = $id_dia
    )
");

if ($verificar->num_rows > 0) {
    echo "Ya tenés una reserva para este día.";
    exit;
}

// Guardar reserva
$stmt = $conexion->prepare("INSERT INTO reservas (cliente_id, turno_id, fecha) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $cliente_id, $turno_id, $hoy);
$stmt->execute();

header("Location: reservar_turno_cliente.php?dia=$id_dia");
exit;
?>
