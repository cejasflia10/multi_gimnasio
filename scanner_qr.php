<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    include("conexion.php");
    $dni = trim($_POST["dni"]);

    $sql = "SELECT c.nombre, c.apellido, m.clases_disponibles, m.fecha_vencimiento
            FROM clientes c
            LEFT JOIN membresias m ON c.id = m.cliente_id
            WHERE c.dni = ? ORDER BY m.fecha_inicio DESC LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $stmt->bind_result($nombre, $apellido, $clases, $vencimiento);

    if ($stmt->fetch()) {
        $mensaje = "✅ $nombre $apellido\n📅 Vencimiento: $vencimiento\n🎯 Clases: $clases";
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
        h1 {
            font-size: 1.6em;
            margin-bottom: 20px;
        }
        #reader {
            width: 100%;
            max-width: 400px;
            margin: auto;
            border: 3px solid gold;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        input[type="text"] {
            padding: 10px;
            width: 90%;
            max-width: 400px;
            font-size: 1em;
            border-radius: 5px;
            border: none;
            margin-bottom: 10px;
        }
        button {
            background-color: gold;
            border: none;
            padding: 12px 20px;
            font-size: 1em;
            cursor: pointer;
            border-radius: 5px;
            margin: 5px;
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

    <form method="POST">
        <input type="text" name="dni" placeholder="Ingresar DNI manualmente">
        <button type="submit">Verificar DNI</button>
    </form>

    <?php if (isset($mensaje)) { echo "<pre>$mensaje</pre>"; } ?>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            document.querySelector('input[name="dni"]').value = decodedText;
            document.querySelector('form').submit();
        }

        let html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", { fps: 10, qrbox: 250 });
        html5QrcodeScanner.render(onScanSuccess);
    </script>

</body>
</html>
