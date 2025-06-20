<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR - Fight Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Estilo visual -->
    <style>
        body {
            background-color: black;
            color: gold;
            text-align: center;
            font-family: Arial, sans-serif;
        }
        h1 {
            margin-top: 30px;
        }
        #reader {
            width: 300px;
            margin: 20px auto;
            border: 3px solid gold;
            border-radius: 10px;
        }
        #result {
            margin-top: 20px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Fight Academy</h1>
    <div id="reader"></div>
    <div id="result"></div>

    <!-- Librería QR -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            // Redirige a asistencia QR pasando el valor escaneado como GET
            window.location.href = "registrar_asistencia_qr.php?qr=" + encodeURIComponent(decodedText);
        }

        function onScanError(errorMessage) {
            // Silenciar errores si no hay QR escaneado
        }

        let html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", 
            { fps: 10, qrbox: 250 },
            /* verbose= */ false
        );
        html5QrcodeScanner.render(onScanSuccess, onScanError);
    </script>
</body>
</html>
