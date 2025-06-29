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

// Obtener ID del cliente (debería ser redundante si ya tenés cliente_id en sesión)
$cliente = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'")->fetch_assoc();
$id_cliente = $cliente['id'] ?? 0;

if (!$id_cliente) {
    die("Cliente no encontrado.");
}

// Validar membresía activa
$membresia = $conexion->query("SELECT * FROM membresias 
    WHERE cliente_id = $id_cliente 
    AND fecha_vencimiento >= CURDATE() 
    AND clases_disponibles > 0 
    ORDER BY id DESC LIMIT 1");

if ($membresia->num_rows === 0) {
    die("No tienes clases disponibles.");
}

$m = $membresia->fetch_assoc();
$id_membresia = $m['id'];

// Verificar si ya reservó ese turno
$ya_reservo = $conexion->query("SELECT * FROM reservas WHERE turno_id = $id_turno AND cliente_id = $id_cliente");
if ($ya_reservo->num_rows > 0) {
    die("Ya reservaste este turno.");
}

// Guardar reserva
$conexion->query("INSERT INTO reservas (turno_id, cliente_id, fecha) VALUES ($id_turno, $id_cliente, CURDATE())");

// Descontar clase
$conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = $id_membresia");

echo "<script>alert('Reserva realizada correctamente'); location.href='cliente_turnos.php';</script>";
