<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
header('Content-Type: text/html; charset=UTF-8');

date_default_timezone_set('America/Argentina/Buenos_Aires');
$fecha = date('Y-m-d');
$hora = date('H:i:s');

$dni = $_POST['dni'] ?? '';

if (!$dni) {
    echo "<div style='color:orange;'>⚠️ DNI no recibido</div>";
    exit;
}

// Paso 1: Buscar cliente
$cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");

if (!$cliente_q || $cliente_q->num_rows === 0) {
    echo "<div style='color:red;'>❌ Cliente no encontrado</div>";
    exit;
}

$cliente = $cliente_q->fetch_assoc();
$cliente_id = $cliente['id'];
$nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];

echo "<div style='color:cyan;'>✅ Cliente encontrado: $nombre</div>";

// Paso 2: Verificar membresía
$membresia_q = $conexion->query("
    SELECT m.*, p.nombre AS plan_nombre 
    FROM membresias m
    JOIN planes p ON m.plan_id = p.id
    WHERE m.cliente_id = $cliente_id
    ORDER BY m.fecha_vencimiento DESC
    LIMIT 1
");

if (!$membresia_q || $membresia_q->num_rows === 0) {
    echo "<div style='color:orange;'>⚠️ $nombre no tiene membresía registrada</div>";
    exit;
}

$membresia = $membresia_q->fetch_assoc();
$clases = (int)$membresia['clases_restantes'];
$vencimiento = $membresia['fecha_vencimiento'];
$plan_nombre = $membresia['plan_nombre'];

// Paso 3: ¿Ya asistió hoy?
$ya_asistio = $conexion->query("
    SELECT 1 FROM asistencias 
    WHERE cliente_id = $cliente_id AND fecha = '$fecha'
");

$ya_ingreso = $ya_asistio && $ya_asistio->num_rows > 0;

// Paso 4: Verifica si puede ingresar
if (($clases > 0 && $vencimiento >= $fecha) || $plan_nombre === 'FREE PASS') {
    // FREE PASS puede ingresar varias veces
    if (!$ya_ingreso || $plan_nombre === 'FREE PASS') {
        // Registrar asistencia
        $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");

        if ($plan_nombre !== 'FREE PASS') {
            $conexion->query("UPDATE membresias SET clases_restantes = clases_restantes - 1 WHERE id = {$membresia['id']}");
            $clases--;
        }

        echo "<div style='color:lime;'>✅ Asistencia registrada: $nombre</div>";
        echo "<div style='color:white;'>📅 Vence: $vencimiento<br>🎯 Clases restantes: $clases<br>🕒 Hora: $hora</div>";
    } else {
        echo "<div style='color:gold;'>⚠️ $nombre ya registró asistencia hoy.</div>";
        echo "<div style='color:white;'>📅 Vence: $vencimiento<br>🎯 Clases: $clases</div>";
    }
} else {
    echo "<div style='color:orange;'>⚠️ $nombre no tiene clases disponibles o está vencido</div>";
    echo "<div style='color:white;'>📅 Vence: $vencimiento<br>🎯 Clases: $clases</div>";
}
?>
