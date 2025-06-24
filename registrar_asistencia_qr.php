<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if (!isset($_GET['dni'])) {
    echo json_encode(["success" => false, "mensaje" => "DNI no recibido"]);
    exit;
}

$dni = $conexion->real_escape_string($_GET["dni"]);
$fecha_actual = date("Y-m-d");
$hora_actual = date("H:i:s");

$clientes_result = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE dni = '$dni'");
if ($clientes_result->num_rows === 0) {
    echo json_encode(["success" => false, "mensaje" => "DNI no encontrado."]);
    exit;
}

$cliente = $clientes_result->fetch_assoc();
$cliente_id = $cliente['id'];

$membresia_result = $conexion->query("
    SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id
    AND fecha_vencimiento >= CURDATE()
    AND clases_restantes > 0
    ORDER BY fecha_vencimiento DESC
    LIMIT 1
");

if ($membresia_result->num_rows === 0) {
    echo json_encode(["success" => false, "mensaje" => "Sin membresía activa o sin clases."]);
    exit;
}

$membresia = $membresia_result->fetch_assoc();
$id_membresia = $membresia['id'];
$clases_restantes = $membresia['clases_restantes'] - 1;
$vencimiento = $membresia['fecha_vencimiento'];

// Descontar clase
$conexion->query("UPDATE membresias SET clases_restantes = $clases_restantes WHERE id = $id_membresia");

// Registrar asistencia
$conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha_actual', '$hora_actual')");

echo json_encode([
    "success" => true,
    "nombre" => $cliente['apellido'] . " " . $cliente['nombre'],
    "clases" => $clases_restantes,
    "vencimiento" => $vencimiento
]);
