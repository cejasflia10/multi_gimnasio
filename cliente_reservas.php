<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cliente_id'])) {
    die("Acceso inválido.");
}

include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'];
$dni = $_SESSION['cliente_dni'] ?? '';
$id_turno = $_GET['id_turno'] ?? 0;

if (!$id_turno || !$dni) {
    die("Datos incompletos.");
}

// Validar membresía activa y clases disponibles
$membresia_q = $conexion->query("SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id 
    AND fecha_vencimiento >= CURDATE() 
    AND clases_disponibles > 0 
    ORDER BY id DESC LIMIT 1");

if ($membresia_q->num_rows === 0) {
    die("No tenés una membresía activa o sin clases disponibles.");
}
$m = $membresia_q->fetch_assoc();
$id_membresia = $m['id'];

// Obtener fecha correspondiente al turno
$turno_q = $conexion->query("SELECT dia_id FROM turnos WHERE id = $id_turno");
$turno = $turno_q->fetch_assoc();
$dia_id = $turno['dia_id'];

$fecha_reserva = date('Y-m-d', strtotime("this week +" . ($dia_id - 1) . " days"));

// Validar si ya reservó ese día
$ya_reservo = $conexion->query("SELECT * FROM reservas WHERE cliente_id = $cliente_id AND fecha = '$fecha_reserva'");
if ($ya_reservo->num_rows > 0) {
    die("Ya tenés una reserva para ese día.");
}

// Verificar cupos
$cupos_q = $conexion->query("SELECT cupos_maximos FROM turnos WHERE id = $id_turno");
$cupos = $cupos_q->fetch_assoc()['cupos_maximos'] ?? 0;

$usados_q = $conexion->query("SELECT COUNT(*) AS usados FROM reservas WHERE turno_id = $id_turno AND fecha = '$fecha_reserva'");
$usados = $usados_q->fetch_assoc()['usados'] ?? 0;

if ($usados >= $cupos) {
    die("No hay cupos disponibles en este turno.");
}

// Registrar reserva
$conexion->query("INSERT INTO reservas (turno_id, cliente_id, fecha) VALUES ($id_turno, $cliente_id, '$fecha_reserva')");

// Descontar clase
$conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = $id_membresia");

echo "<script>alert('Reserva realizada correctamente'); location.href='cliente_turnos.php';</script>";
