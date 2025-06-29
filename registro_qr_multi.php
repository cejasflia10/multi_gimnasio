<?php
include 'conexion.php';
session_start();

$codigo = $_GET['codigo'] ?? $_POST['codigo'] ?? '';
$codigo = trim($codigo);

if (!$codigo) {
    die("<h2 style='color: red;'>❌ Código QR no recibido.</h2>");
}

$fecha = date('Y-m-d');
$hora = date('H:i:s');

if (substr($codigo, 0, 2) === 'P-') {
    $dni = substr($codigo, 2);
    $profesor_q = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni'");
    if ($profesor_q->num_rows > 0) {
        $profesor = $profesor_q->fetch_assoc();
        $profesor_id = $profesor['id'];
        $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha', '$hora')");
        echo "<h2 style='color: lime;'>✅ Profesor registrado correctamente.</h2>";
    } else {
        echo "<h2 style='color: red;'>❌ Profesor no encontrado.</h2>";
    }

} elseif (substr($codigo, 0, 2) === 'C-') {
    $dni = substr($codigo, 2);
    $cliente_q = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'");
    if ($cliente_q->num_rows > 0) {
        $cliente = $cliente_q->fetch_assoc();
        $cliente_id = $cliente['id'];
        $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
        echo "<h2 style='color: lime;'>✅ Cliente registrado correctamente.</h2>";
    } else {
        echo "<h2 style='color: red;'>❌ Cliente no encontrado.</h2>";
    }

} else {
    echo "<h2 style='color: orange;'>⚠️ Código QR inválido. Debe comenzar con C- o P-.</h2>";
}
?>
