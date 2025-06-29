
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!isset($_SESSION['cliente_id'], $_SESSION['cliente_dni'], $_GET['id_turno'])) {
    die("Acceso inválido o datos incompletos.");
}

$cliente_id = $_SESSION['cliente_id'];
$dni = $_SESSION['cliente_dni'];
$id_turno = intval($_GET['id_turno']);

// Validar membresía activa
$membresia = $conexion->query("SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id 
    AND fecha_vencimiento >= CURDATE() 
    AND clases_disponibles > 0 
    ORDER BY id DESC LIMIT 1");

if ($membresia->num_rows === 0) {
    die("No tienes clases disponibles.");
}

$m = $membresia->fetch_assoc();
$id_membresia = $m['id'];

// Verificar si ya reservó hoy
$fecha_hoy = date('Y-m-d');
$ya_reservo = $conexion->query("SELECT * FROM reservas WHERE turno_id = $id_turno AND cliente_id = $cliente_id AND fecha = '$fecha_hoy'");
if ($ya_reservo->num_rows > 0) {
    die("Ya reservaste este turno hoy.");
}

// Guardar reserva
$conexion->query("INSERT INTO reservas (turno_id, cliente_id, fecha, fecha_reserva, estado) 
                  VALUES ($id_turno, $cliente_id, '$fecha_hoy', NOW(), 'Reservado')");

// Descontar clase
$conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = $id_membresia");

echo "<script>alert('Reserva realizada correctamente'); location.href='cliente_turnos.php';</script>";
?>
