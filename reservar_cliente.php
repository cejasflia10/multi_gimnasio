<?php
include 'conexion.php';
if (!isset($_GET['dni']) || !isset($_GET['id_turno'])) {
    die("Acceso inválido.");
}
$dni = $_GET['dni'];
$id_turno = $_GET['id_turno'];

// Obtener ID del cliente
$cliente = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'")->fetch_assoc();
$id_cliente = $cliente['id'];

// Validar membresía
$membresia = $conexion->query("SELECT * FROM membresias 
    WHERE id_cliente = $id_cliente 
    AND fecha_vencimiento >= CURDATE() 
    AND clases_disponibles > 0 
    ORDER BY id DESC LIMIT 1");

if ($membresia->num_rows === 0) {
    die("No tienes clases disponibles.");
}
$m = $membresia->fetch_assoc();
$id_membresia = $m['id'];

// Verificar si ya reservó ese turno
$ya_reservo = $conexion->query("SELECT * FROM reservas WHERE id_turno = $id_turno AND id_cliente = $id_cliente");
if ($ya_reservo->num_rows > 0) {
    die("Ya reservaste este turno.");
}

// Guardar reserva
$conexion->query("INSERT INTO reservas (id_turno, id_cliente, fecha_reserva) VALUES ($id_turno, $id_cliente, NOW())");

// Descontar clase
$conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = $id_membresia");

echo "<script>alert('Reserva realizada correctamente'); location.href='cliente_turnos.php?dni=$dni';</script>";
