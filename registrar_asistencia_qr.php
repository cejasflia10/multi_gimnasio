<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$dni = $_POST['dni'] ?? '';
$fecha = date('Y-m-d');
$hora = date('H:i:s');

if (empty($dni)) {
    echo "<div style='color: red;'>❌ DNI no recibido.</div>";
    exit;
}

// PASO 1: Buscar cliente
$cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");

if (!$cliente_q || $cliente_q->num_rows === 0) {
    echo "<div style='color: red;'>❌ Cliente no encontrado</div>";
    exit;
}

$cliente = $cliente_q->fetch_assoc();
$cliente_id = $cliente['id'];
$nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];

echo "<div style='color: cyan;'>✅ Cliente encontrado: $nombre</div>";

// PASO 2: Buscar membresía vigente
$membresia_q = $conexion->query("
    SELECT m.*, p.nombre AS nombre_plan 
    FROM membresias m 
    JOIN planes p ON m.plan_id = p.id 
    WHERE m.cliente_id = $cliente_id 
    ORDER BY m.fecha_vencimiento DESC 
    LIMIT 1
");

if (!$membresia_q || $membresia_q->num_rows === 0) {
    echo "<div style='color: orange;'>⚠️ $nombre no tiene membresía registrada</div>";
    exit;
}

$membresia = $membresia_q->fetch_assoc();
$clases = (int)$membresia['clases_disponibles'];
$vto = $membresia['fecha_vencimiento'];
$plan_nombre = $membresia['nombre_plan'];
$membresia_id = $membresia['id'];

echo "<div style='color: green;'>✅ Membresía: clases = $clases, vence = $vto</div>";

// PASO 3: Verificar si ya asistió hoy
$ya_asistio = $conexion->query("SELECT 1 FROM asistencias WHERE cliente_id = $cliente_id AND fecha = '$fecha'");

if ($ya_asistio && $ya_asistio->num_rows > 0) {
    echo "<div style='color: gold;'>⚠️ $nombre ya registró asistencia hoy.</div>";
    exit;
}

// PASO 4: Verificar clases y vencimiento
if ($clases > 0 && $vto >= $fecha) {
    // Registrar asistencia y descontar clase
    $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
    $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = $membresia_id");

    echo "<div style='color: green;'>✅ Asistencia registrada</div>";
    echo "<div style='color: white;'>📅 Vence: $vto<br>🎯 Clases restantes: " . ($clases - 1) . "<br>🕒 $hora</div>";
} else {
    echo "<div style='color: orange;'>⚠️ $nombre no tiene clases disponibles o está vencido</div>";
    echo "<div style='color: white;'>📅 Vence: $vto<br>🎯 Clases: $clases</div>";
}
?>
