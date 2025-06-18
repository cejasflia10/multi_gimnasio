<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencia por QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            margin: 0 auto;
        }
        .info-box {
            border: 2px solid gold;
            padding: 15px;
            margin-top: 20px;
            border-radius: 10px;
        }
        img.logo {
            max-width: 150px;
            margin-bottom: 20px;
        }
    </style>
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
    <img src="logo.png" alt="Logo Fight Academy" class="logo">
    <h1>Registro de Asistencia por QR</h1>
    <div id="reader"></div>
    <div id="resultado" class="info-box"></div>

    <script>
        function registrarAsistencia(dato) {
            fetch('procesar_asistencia_qr.php?dato=' + encodeURIComponent(dato))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('resultado').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('resultado').innerHTML = 'Error: ' + error;
                });
        }

        const html5QrCode = new Html5Qrcode("reader");
        const qrConfig = { fps: 10, qrbox: 250 };

        html5QrCode.start(
            { facingMode: "environment" },
            qrConfig,
            qrCodeMessage => {
                html5QrCode.stop().then(() => {
                    registrarAsistencia(qrCodeMessage);
                });
            },
            errorMessage => {
                // Puedes mostrar errores si quieres
            }
        ).catch(err => {
            document.getElementById('resultado').innerHTML = 'No se pudo acceder a la cámara: ' + err;
        });
    </script>
</body>
</html>
