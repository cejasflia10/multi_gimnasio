<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

header('Content-Type: text/html; charset=UTF-8');

if (!isset($_POST['dni']) || empty(trim($_POST['dni']))) {
    echo "<div style='color: yellow;'>❌ DNI no recibido</div>";
    exit;
}

$dni = trim($_POST['dni']);
$fecha = date("Y-m-d");
$hora = date("H:i:s");

$cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");

if ($cliente_q && $cliente_q->num_rows > 0) {
    $cliente = $cliente_q->fetch_assoc();
    $cliente_id = $cliente['id'];
    $nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];

    $membresia_q = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id AND fecha_vencimiento >= '$fecha' ORDER BY fecha_vencimiento DESC LIMIT 1");

    if ($membresia_q && $membresia_q->num_rows > 0) {
        $membresia = $membresia_q->fetch_assoc();
        $clases = (int)$membresia['clases_restantes'];
        $vto = $membresia['fecha_vencimiento'];

        if ($clases > 0) {
            $ya_asistio = $conexion->query("SELECT 1 FROM asistencias WHERE cliente_id = $cliente_id AND fecha = '$fecha' LIMIT 1");
            if ($ya_asistio && $ya_asistio->num_rows > 0) {
                echo "<div style='color: orange;'>⚠️ $nombre ya registró asistencia hoy.</div>
                      <div style='color: gold;'>📅 Vence: $vto<br>🎯 Clases restantes: $clases</div>";
            } else {
                $conexion->query("UPDATE membresias SET clases_restantes = clases_restantes - 1 WHERE id = {$membresia['id']}");
                $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
                echo "<div style='color: lime;'>✅ $nombre - Asistencia registrada</div>
                      <div style='color: gold;'>📅 Vence: $vto<br>🎯 Clases restantes: " . ($clases - 1) . "<br>🕒 $hora</div>";
            }
        } else {
            echo "<div style='color: yellow;'>⚠️ $nombre no tiene clases disponibles</div>
                  <div style='color: gold;'>📅 Vence: $vto<br>🎯 Clases restantes: $clases</div>";
        }
    } else {
        echo "<div style='color: yellow;'>⚠️ $nombre no tiene membresía activa</div>";
    }
} else {
    echo "<div style='color: red;'>❌ Cliente no encontrado</div>";
}
?>
