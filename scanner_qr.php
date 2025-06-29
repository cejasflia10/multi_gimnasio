<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escanear QR - Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        h1 {
            color: gold;
            font-size: 24px;
            margin-bottom: 20px;
        }
        video {
            width: 100%;
            max-width: 400px;
            border: 4px solid gold;
            border-radius: 8px;
        }
        .error {
            color: red;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>📷 Escaneo QR para Ingreso Profesor</h1>
    <video id="preview"></video>
    <div class="error" id="error"></div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const errorDiv = document.getElementById("error");

        function iniciarScanner() {
            const html5QrCode = new Html5Qrcode("preview");

            Html5Qrcode.getCameras().then(cameras => {
                if (cameras.length === 0) {
                    errorDiv.textContent = "No se detectó cámara.";
                    return;
                }

                html5QrCode.start(
                    cameras[0].id,
                    {
                        fps: 10,
                        qrbox: 250
                    },
                    qrCodeMessage => {
                        // Esperamos que el QR tenga el DNI directamente (solo números)
                        const dni = qrCodeMessage.trim();

                        if (/^\d{6,10}$/.test(dni)) {
                            html5QrCode.stop().then(() => {
                                window.location.href = `registrar_asistencia_profesor.php?dni=${dni}`;
                            });
                        } else {
                            errorDiv.textContent = "QR no válido (debe contener solo DNI).";
                        }
                    },
                    error => {
                        // Silenciar errores de escaneo continuo
                    }
                ).catch(err => {
                    errorDiv.textContent = "No se pudo acceder a la cámara: " + err;
                });
            }).catch(err => {
                errorDiv.textContent = "Error al obtener cámara: " + err;
            });
        }

        window.addEventListener('load', iniciarScanner);
    </script>
</body>
</html>
