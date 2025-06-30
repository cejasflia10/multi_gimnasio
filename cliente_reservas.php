<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Verificar si el cliente está logueado
$cliente_id = $_SESSION['cliente_id'] ?? null;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;

if (!$cliente_id) {
    header("Location: cliente_turnos.php");
    exit;
}

// Verificar turno recibido
if (!isset($_GET['id_turno'])) {
    echo "Turno no especificado.";
    exit;
}

$turno_id = intval($_GET['id_turno']);
$hoy = date('Y-m-d');

// Verificar que tenga membresía activa y clases disponibles
$membresia_q = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id AND fecha_vencimiento >= CURDATE() AND clases_disponibles > 0 ORDER BY id DESC LIMIT 1");
$membresia = $membresia_q->fetch_assoc();

if (!$membresia) {
    echo "<p style='color:red;'>No tenés una membresía activa o sin clases disponibles.</p>";
    echo "<a href='cliente_turnos.php' style='color:gold;'>Volver</a>";
    exit;
}

// Verificar que exista el turno
$turno_q = $conexion->query("SELECT * FROM turnos WHERE id = $turno_id");
$turno = $turno_q->fetch_assoc();

if (!$turno) {
    echo "Turno no encontrado.";
    exit;
}

$dia_turno = $turno['dia'];
$fecha_turno = date('Y-m-d', strtotime("this week " . $dia_turno));

// Verificar si ya tiene reserva para ese día
$ya_reservado_q = $conexion->query("
    SELECT r.id FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    WHERE r.cliente_id = $cliente_id AND r.fecha = '$fecha_turno' AND r.estado = 'activo'
");
if ($ya_reservado_q->num_rows > 0) {
    echo "<p style='color:red;'>Ya tenés una reserva activa para ese día.</p>";
    echo "<a href='cliente_turnos.php' style='color:gold;'>Volver</a>";
    exit;
}

// Verificar cupos disponibles
$reservas_q = $conexion->query("SELECT COUNT(*) AS total FROM reservas WHERE turno_id = $turno_id AND fecha = '$fecha_turno'");
$reservadas = $reservas_q->fetch_assoc()['total'];
$cupos_maximos = $turno['cupos_maximos'];

if ($reservadas >= $cupos_maximos) {
    echo "<p style='color:red;'>El turno ya no tiene cupos disponibles.</p>";
    echo "<a href='cliente_turnos.php' style='color:gold;'>Volver</a>";
    exit;
}

// Registrar la reserva
$conexion->query("INSERT INTO reservas (cliente_id, turno_id, fecha, estado) VALUES ($cliente_id, $turno_id, '$fecha_turno', 'activo')");

// Descontar clase
$conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = " . $membresia['id']);

echo "<p style='color:lightgreen;'>✅ ¡Reserva confirmada!</p>";
echo "<a href='cliente_turnos.php' style='color:gold;'>Volver</a>";
?>
