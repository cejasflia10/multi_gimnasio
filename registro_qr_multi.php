<?php
include 'conexion.php';
session_start();

$codigo = $_GET['codigo'] ?? $_POST['codigo'] ?? '';
$codigo = trim($codigo);

$fecha = date('Y-m-d');
$hora = date('H:i:s');

function mostrarMensaje($mensaje, $color) {
    echo "<html><head><meta http-equiv='refresh' content='3;url=scanner_qr.php'>
        <style>body{background:#000;color:$color;font-family:Arial;text-align:center;padding-top:100px;font-size:22px}</style>
        </head><body>$mensaje<br><br><small>Redirigiendo...</small></body></html>";
    exit;
}

if (!$codigo) {
    mostrarMensaje("❌ Código QR no recibido.", "red");
}

if (substr($codigo, 0, 2) === 'P-') {
    $dni = substr($codigo, 2);
    $profesor_q = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni'");
    if ($profesor_q->num_rows > 0) {
        $profesor = $profesor_q->fetch_assoc();
        $profesor_id = $profesor['id'];
        $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha', '$hora')");
        mostrarMensaje("✅ Profesor registrado correctamente.", "lime");
    } else {
        mostrarMensaje("❌ Profesor no encontrado.", "red");
    }

} elseif (substr($codigo, 0, 2) === 'C-') {
    $dni = substr($codigo, 2);
    $cliente_q = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'");
    if ($cliente_q->num_rows > 0) {
        $cliente = $cliente_q->fetch_assoc();
        $cliente_id = $cliente['id'];
        $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
        mostrarMensaje("✅ Cliente registrado correctamente.", "lime");
    } else {
        mostrarMensaje("❌ Cliente no encontrado.", "red");
    }

} else {
    mostrarMensaje("⚠️ Código QR inválido.", "orange");
}
?>
