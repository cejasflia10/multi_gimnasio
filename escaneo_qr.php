<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "conexion.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo de QR - Ingreso al Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 20px;
        }
        #qr-video {
            width: 90%%;
            max-width: 600px;
            border: 5px solid gold;
            border-radius: 10px;
            margin-top: 20px;
        }
        #resultado {
            margin-top: 20px;
            font-size: 20px;
            font-weight: bold;
            color: white;
        }
    </style>
</head>
<body>
    <h1>📷 Escaneo de QR - Ingreso al Gimnasio</h1>
    <video id="qr-video"></video>
    <div id="resultado"></div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const videoElem = document.getElementById("qr-video");
        const resultadoElem = document.getElementById("resultado");

        function procesarQR(dato) {
            fetch("registrar_asistencia_qr.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "dni=" + encodeURIComponent(dato)
            })
            .then(response => response.text())
            .then(data => {
                resultadoElem.innerHTML = data;
                html5QrCode.stop();
            });
        }

        const html5QrCode = new Html5Qrcode("qr-video");
        Html5Qrcode.getCameras().then(devices => {
            if (devices && devices.length) {
                html5QrCode.start(
                    { facingMode: "environment" },
                    {
                        fps: 10,
                        qrbox: 250
                    },
                    qrCodeMessage => {
                        procesarQR(qrCodeMessage);
                    }
                ).catch(err => {
                    resultadoElem.innerHTML = "Error iniciando cámara: " + err;
                });
            } else {
                resultadoElem.innerHTML = "No se encontraron cámaras.";
            }
        }).catch(err => {
            resultadoElem.innerHTML = "Error al obtener cámaras: " + err;
        });
    </script>
</body>
</html>
