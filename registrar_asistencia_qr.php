<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

header('Content-Type: text/html; charset=UTF-8');

$dni = trim($_POST['dni'] ?? '');
$fecha = date('Y-m-d');
$hora = date('H:i:s');

if ($dni === '') {
    echo "<div style='color: yellow;'>❌ DNI no recibido</div>";
    exit;
}

// PASO 1: Cliente
$cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");
if (!$cliente_q || $cliente_q->num_rows === 0) {
    echo "<div style='color: red;'>❌ Cliente no encontrado</div>";
    exit;
}
$cliente = $cliente_q->fetch_assoc();
$cliente_id = $cliente['id'];
$nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];
echo "<div style='color: cyan;'>✅ Cliente encontrado: $nombre</div>";

// PASO 2: Membresía
$membresia_q = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id ORDER BY fecha_vencimiento DESC LIMIT 1");
if (!$membresia_q || $membresia_q->num_rows === 0) {
    echo "<div style='color: orange;'>⚠️ $nombre no tiene membresía registrada</div>";
    exit;
}
$membresia = $membresia_q->fetch_assoc();
$clases = (int)$membresia['clases_restantes'];
$vto = $membresia['fecha_vencimiento'];
echo "<div style='color: green;'>✅ Membresía: clases = $clases, vence = $vto</div>";

// PASO 3: Ya asistio hoy?
$ya_asistio = $conexion->query("SELECT 1 FROM asistencias WHERE cliente_id = $cliente_id AND fecha = '$fecha' LIMIT 1");
if ($ya_asistio && $ya_asistio->num_rows > 0) {
    echo "<div style='color: gold;'>⚠️ $nombre ya registró asistencia hoy.</div>";
    exit;
}

// PASO 4: Verifica clases y vencimiento
if (($clases > 0 || $plan_nombre === 'FREE PASS') && $vto >= $fecha) {
    // Registrar asistencia (solo restar clase si no es FREE PASS)
    if ($plan_nombre !== 'FREE PASS') {
        $conexion->query("UPDATE membresias SET clases_restantes = clases_restantes - 1 WHERE id = {$membresia['id']}");
    }
    $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
    echo "<div class='exito'>✅ $nombre - Asistencia registrada</div>
          <div class='info'>📅 Vence: $vto<br>🎯 Clases restantes: " . ($plan_nombre === 'FREE PASS' ? '∞' : $clases - 1) . "<br>🕒 $hora</div>";
}
