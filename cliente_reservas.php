<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$cliente_id = $_SESSION['cliente_id'] ?? null;
$dni = $_SESSION['cliente_dni'] ?? null;
$id_turno = $_GET['id_turno'] ?? null;

if (!$cliente_id || !$id_turno || !$dni) {
    die("Acceso inválido o datos incompletos.");
}

// Validar membresía activa con clases disponibles
$membresia = $conexion->query("
    SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id 
    AND fecha_vencimiento >= CURDATE() 
    AND clases_disponibles > 0 
    ORDER BY id DESC LIMIT 1
");

if ($membresia->num_rows === 0) {
    die("⚠️ No tenés clases disponibles o membresía activa.");
}

$m = $membresia->fetch_assoc();
$id_membresia = $m['id'];

// Determinar la fecha del turno según el día del turno
$turno_q = $conexion->query("SELECT dia FROM turnos WHERE id = $id_turno");
$turno = $turno_q->fetch_assoc();

if (!$turno) {
    die("Turno no encontrado.");
}

$dia_turno = $turno['dia']; // Ej: 'Lunes', 'Martes', etc.
// Convertir día a número
$dias_semana = ['Lunes'=>1,'Martes'=>2,'Miércoles'=>3,'Jueves'=>4,'Viernes'=>5,'Sábado'=>6];
$dia_num = $dias_semana[$dia_turno] ?? null;

if (!$dia_num) {
    die("Día del turno inválido.");
}

// Calcular la fecha de reserva (esta semana)
$fecha_reserva = date('Y-m-d', strtotime("this week +" . ($dia_num - 1) . " days"));

// Verificar si ya reservó
$ya_reservo = $conexion->query("
    SELECT * FROM reservas 
    WHERE cliente_id = $cliente_id AND turno_id = $id_turno AND fecha = '$fecha_reserva'
");

if ($ya_reservo->num_rows > 0) {
    die("⚠️ Ya reservaste este turno.");
}

// Insertar reserva
$conexion->query("
    INSERT INTO reservas (cliente_id, turno_id, fecha, fecha_reserva, estado) 
    VALUES ($cliente_id, $id_turno, '$fecha_reserva', NOW(), 'Reservado')
");

// Descontar clase
$conexion->query("
    UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = $id_membresia
");

echo "<script>alert('✅ Turno reservado correctamente'); location.href='cliente_turnos.php';</script>";
?>
