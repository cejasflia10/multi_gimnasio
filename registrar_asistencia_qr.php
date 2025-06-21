<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$dni = isset($_GET['qr']) ? trim($_GET['qr']) : '';
$fecha_actual = date("Y-m-d");
$hora_actual = date("H:i:s");

// Validación rápida
if (empty($dni)) {
    echo "<h2 style='color:red'>No se recibió ningún código QR.</h2>";
    exit;
}

// Buscar cliente
$consulta = $conexion->prepare("SELECT id, nombre, apellido FROM clientes WHERE dni = ?");
$consulta->bind_param("s", $dni);
$consulta->execute();
$resultado = $consulta->get_result();

if ($resultado->num_rows === 0) {
    echo "<h2 style='color:red'>Cliente no encontrado.</h2>";
    exit;
}

$cliente = $resultado->fetch_assoc();
$id_cliente = $cliente['id'];
$nombre_completo = $cliente['nombre'] . " " . $cliente['apellido'];

// Buscar membresía vigente
$membresia = $conexion->prepare("SELECT id, clases_disponibles, fecha_vencimiento FROM membresias WHERE cliente_id = ? ORDER BY fecha_inicio DESC LIMIT 1");
$membresia->bind_param("i", $id_cliente);
$membresia->execute();
$res_membresia = $membresia->get_result();

if ($res_membresia->num_rows === 0) {
    echo "<h2 style='color:red'>No hay membresía registrada.</h2>";
    exit;
}

$datos_membresia = $res_membresia->fetch_assoc();
$clases = (int)$datos_membresia['clases_disponibles'];
$vencimiento = $datos_membresia['fecha_vencimiento'];

// Verificar fecha de vencimiento
if ($vencimiento < $fecha_actual) {
    echo "<h2 style='color:red'>La membresía está vencida. Venció el: $vencimiento</h2>";
    exit;
}

// Verificar clases
if ($clases <= 0) {
    echo "<h2 style='color:red'>No tiene clases disponibles.</h2>";
    exit;
}

// Registrar asistencia
$insert = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, ?, ?)");
$insert->bind_param("iss", $id_cliente, $fecha_actual, $hora_actual);
$insert->execute();

// Descontar clase
$conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = " . $datos_membresia['id']);

echo "<h2 style='color:green'>Bienvenido $nombre_completo</h2>";
echo "<p>Ingreso registrado el <strong>$fecha_actual</strong> a las <strong>$hora_actual</strong></p>";
echo "<p>Clases restantes: <strong>" . ($clases - 1) . "</strong></p>";
echo "<p>Vencimiento: <strong>$vencimiento</strong></p>";
?>
