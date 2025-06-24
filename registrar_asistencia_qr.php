<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$dni_detectado = '';
$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["qr_dato"])) {
    $dni_detectado = trim($_POST["qr_dato"]);

    // Verificar si el cliente tiene membresía activa
    $query = "
        SELECT c.id AS cliente_id, c.nombre, c.apellido, m.id AS membresia_id, m.clases_disponibles, m.fecha_vencimiento
        FROM clientes c
        JOIN membresias m ON c.id = m.cliente_id
        WHERE c.dni = '$dni_detectado'
        AND m.fecha_vencimiento >= CURDATE()
        AND m.clases_disponibles > 0
        ORDER BY m.fecha_vencimiento DESC
        LIMIT 1
    ";

    $resultado = $conexion->query($query);

    if ($resultado && $resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $cliente_id = $fila['cliente_id'];
        $membresia_id = $fila['membresia_id'];
        $nombre = $fila['nombre'];
        $apellido = $fila['apellido'];
        $clases_disponibles = $fila['clases_disponibles'];
        $vencimiento = $fila['fecha_vencimiento'];

        // Descontar una clase
        $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = $membresia_id");

        // Registrar asistencia
        $fecha = date("Y-m-d");
        $hora = date("H:i:s");
        $conexion->query("INSERT INTO asistencias_clientes (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");

        $mensaje = "✅ $nombre $apellido ($dni_detectado) - Asistencia registrada. Clases restantes: " . ($clases_disponibles - 1);
    } else {
        $mensaje = "⚠️ El DNI $dni_detectado no tiene una membresía activa o clases disponibles.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencia QR</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        #reader {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }
        #mensaje {
            margin-top: 20px;
            font-size: 18px;
            font-weight: bold;
        }
        .volver-btn {
            margin-top: 20px;
            background-color: gold;
            color: black;
            padding: 10px 20px;
            border: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>

    <div id="reader"></div>
    <div id="mensaje"><?= $mensaje ? $mensaje : "Esperando escaneo..." ?></div>

    <form id="formulario" method="POST" style="display: none;">
        <input type="hidden" name="qr_dato" id="qr_dato">
    </form>

    <script>
        function onScanSuccess(decodedText) {
            document.getElementById("qr_dato").value = decodedText;
            document.getElementById("formulario").submit();
        }

        function onScanError(errorMessage) {
            console.warn("Error escaneo:", errorMessage);
        }

        const html5QrCode = new Html5Qrcode("reader");
        html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: 250
            },
            onScanSuccess,
            onScanError
        );
    </script>

    <button class="volver-btn" onclick="window.location.href='index.php'">Volver al menú</button>
</body>
</html>
