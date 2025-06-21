<?php
include 'conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR para Ingreso</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { background-color: black; color: gold; text-align: center; font-family: sans-serif; }
        #reader { width: 300px; margin: auto; }
    </style>
</head>
<body>
    <h2>Escaneo QR para Ingreso</h2>
    <div id="reader"></div>
    <div id="resultado" style="margin-top: 20px;"></div>
    <script>
        function onScanSuccess(decodedText) {
            fetch('scanner_qr.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'dni=' + encodeURIComponent(decodedText)
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('resultado').innerHTML = data;
            })
            .catch(err => {
                document.getElementById('resultado').innerHTML = '<p style="color:red;">Error al registrar</p>';
            });
        }
        const html5QrCode = new Html5Qrcode("reader");
        Html5Qrcode.getCameras().then(devices => {
            if (devices.length) {
                html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: 250 },
                    onScanSuccess
                );
            }
        });
    </script>
</body>
</html>
