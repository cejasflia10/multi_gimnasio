<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$dni = trim($_POST['dni'] ?? '');
$fecha = date('Y-m-d');
$hora = date('H:i:s');

if ($dni === '') {
    echo "<div style='color: yellow;'>❌ DNI no recibido</div>";
    exit;
}

// Buscar cliente
$cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");
if (!$cliente_q || $cliente_q->num_rows === 0) {
    echo "<div style='color: red;'>❌ Cliente no encontrado</div>";
    exit;
}

$cliente = $cliente_q->fetch_assoc();
$cliente_id = $cliente['id'];
$nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];

// Buscar membresía más reciente
$membresia_q = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id ORDER BY fecha_vencimiento DESC LIMIT 1");
if (!$membresia_q || $membresia_q->num_rows === 0) {
    echo "<div style='color: yellow;'>⚠️ $nombre no tiene membresía registrada</div>";
    exit;
}

$membresia = $membresia_q->fetch_assoc();
$clases = (int) $membresia['clases_restantes'];
$vto = $membresia['fecha_vencimiento'];

// Ya asistió hoy
$asistencia_q = $conexion->query("SELECT * FROM asistencias WHERE cliente_id = $cliente_id AND fecha = '$fecha'");
if ($asistencia_q->num_rows > 0) {
    echo "<div style='color: orange;'>⚠️ $nombre ya registró asistencia hoy.</div>
          <div style='color: gold;'>📅 Vence: $vto<br>🎯 Clases restantes: $clases</div>";
    exit;
}

// Verifica clases y vencimiento
if ($clases > 0 && $vto >= $fecha) {
    // Registrar asistencia
    $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
    // Descontar clase
    $conexion->query("UPDATE membresias SET clases_restantes = clases_restantes - 1 WHERE id = {$membresia['id']}");
    echo "<div style='color: lime;'>✅ $nombre - Asistencia registrada</div>
          <div style='color: gold;'>📅 Vence: $vto<br>🎯 Clases restantes: " . ($clases - 1) . "<br>🕒 $hora</div>";
} else {
    echo "<div style='color: yellow;'>⚠️ $nombre no tiene clases disponibles o su membresía está vencida</div>
          <div style='color: gold;'>📅 Vence: $vto<br>🎯 Clases restantes: $clases</div>";
}
?>
