<?php
include 'conexion.php';
session_start();

$codigo = $_GET['codigo'] ?? $_POST['codigo'] ?? '';
$codigo = trim($codigo);

$fecha = date('Y-m-d');
$hora = date('H:i:s');

function mostrarMensaje($mensaje, $color, $detalles = '') {
    echo "<html><head>
        <style>body{background:#000;color:$color;font-family:Arial;text-align:center;padding-top:100px;font-size:22px}</style>
        </head><body>
        <h2>$mensaje</h2>
        <p><strong>Contenido QR:</strong> $detalles</p>
        <br><a href='scanner_qr.php' style='color:$color'>⬅ Volver</a>
        </body></html>";
    exit;
}

if (!$codigo) {
    mostrarMensaje("❌ Código QR no recibido.", "red", "Código vacío");
}

if (substr($codigo, 0, 2) === 'P-') {
    $dni = substr($codigo, 2);
    $profesor_q = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni'");
    if ($profesor_q->num_rows > 0) {
        $profesor = $profesor_q->fetch_assoc();
        $profesor_id = $profesor['id'];
        $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha', '$hora')");
        mostrarMensaje("✅ Profesor registrado", "lime", $codigo);
    } else {
        mostrarMensaje("❌ Profesor no encontrado", "red", $codigo);
    }
} elseif (substr($codigo, 0, 2) === 'C-') {
    $dni = substr($codigo, 2);
    $cliente_q = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'");
    if ($cliente_q->num_rows > 0) {
        $cliente = $cliente_q->fetch_assoc();
        $cliente_id = $cliente['id'];
        $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
        mostrarMensaje("✅ Cliente registrado", "lime", $codigo);
    } else {
        mostrarMensaje("❌ Cliente no encontrado", "red", $codigo);
    }
} else {
    mostrarMensaje("⚠️ Código QR inválido.", "orange", $codigo);
}
?>
