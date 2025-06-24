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
    <title>Escaneo QR - Asistencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        video {
            width: 100%;
            max-width: 400px;
            border: 2px solid gold;
            margin-top: 10px;
        }
        .mensaje {
            margin-top: 20px;
            font-size: 20px;
        }
    </style>
</head>
<body>
    <h2>Escaneá tu QR</h2>
    <video id="preview"></video>
    <div class="mensaje" id="mensaje"></div>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        const mensaje = document.getElementById("mensaje");

        function procesarQR(dni) {
            fetch("registrar_asistencia_qr.php?dni=" + dni)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mensaje.innerHTML = `
                            ✅ ${data.nombre}<br>
                            🗓️ Vence: ${data.vencimiento}<br>
                            🎟️ Clases restantes: ${data.clases}<br>
                        `;
                    } else {
                        mensaje.innerHTML = "⚠️ " + data.mensaje;
                    }
                    setTimeout(() => {
                        mensaje.innerHTML = "";
                        scanner.resume();
                    }, 5000);
                })
                .catch(error => {
                    mensaje.innerHTML = "❌ Error al procesar QR";
                    setTimeout(() => {
                        mensaje.innerHTML = "";
                        scanner.resume();
                    }, 5000);
                });
        }

        const scanner = new Html5Qrcode("preview");
        scanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            (decodedText) => {
                scanner.pause();
                procesarQR(decodedText.trim());
            },
            (errorMessage) => { /* Ignorar errores */ }
        );
    </script>
</body>
</html>
