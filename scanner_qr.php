<?php
include("conexion.php");
session_start();

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

    $sql = "SELECT c.id, c.nombre, c.apellido, m.id AS membresia_id, m.clases_disponibles, m.fecha_vencimiento
            FROM clientes c
            LEFT JOIN membresias m ON c.id = m.cliente_id
            WHERE c.dni = ? AND c.gimnasio_id = ?
            ORDER BY m.fecha_inicio DESC LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $stmt->bind_result($cliente_id, $nombre, $apellido, $membresia_id, $clases, $vencimiento);

    if ($stmt->fetch()) {
        if ($clases > 0 && $vencimiento >= date('Y-m-d')) {
            $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 
                              WHERE id = $membresia_id");

            $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id)
                              VALUES ($cliente_id, CURDATE(), CURTIME(), $gimnasio_id)");

            $mensaje = "✅ $nombre $apellido\n📅 Vencimiento: $vencimiento\n🎯 Clases restantes: " . ($clases - 1);
        } else {
            $mensaje = "⚠️ Membresía vencida o sin clases.\n📅 Vencimiento: $vencimiento\n🎯 Clases: $clases";
        }
    } else {
        $mensaje = "❌ Cliente no encontrado.";
    }

    $stmt->close();
    $conexion->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR - Fight Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        #reader {
            width: 100%;
            max-width: 400px;
            margin: auto;
            border: 3px solid gold;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        pre {
            background-color: #111;
            color: white;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid gold;
            border-radius: 5px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>

    <h1>Escaneo QR - Fight Academy</h1>

    <div id="reader"></div>

    <form id="qrForm" method="POST" style="display: none;">
        <input type="hidden" name="dni" id="dniInput">
    </form>

    <?php if ($mensaje) { echo "<pre>$mensaje</pre>"; } ?>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            document.getElementById('dniInput').value = decodedText;
            document.getElementById('qrForm').submit();
        }

        let html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", { fps: 10, qrbox: 250 });
        html5QrcodeScanner.render(onScanSuccess);
    </script>

</body>
</html>
