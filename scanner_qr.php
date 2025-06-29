<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR para Ingreso Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
        }
        h1 {
            margin: 20px 0;
        }
        #reader {
            width: 90%;
            max-width: 400px;
            margin: auto;
            border: 4px solid gold;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <h1>📸 Escaneo QR para Ingreso Profesor</h1>
    <div id="reader"></div>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            // Validar que el QR empiece con 'P-' (por ejemplo)
            if(decodedText.startsWith('P-')) {
                // Extraer el DNI (todo lo que sigue de P-)
                const dni = decodedText.substring(2);
                window.location.href = "registrar_asistencia_profesor.php?dni=" + encodeURIComponent(dni);
            } else {
                alert("❌ QR no válido para profesor");
            }
        }

        function onScanError(errorMessage) {
            // Solo mostramos error en consola para no molestar al usuario
            console.log("Error de escaneo: ", errorMessage);
        }

        const html5QrCode = new Html5Qrcode("reader");
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };

        Html5Qrcode.getCameras().then(cameras => {
            if (cameras && cameras.length) {
                html5QrCode.start(
                    cameras[0].id,
                    config,
                    onScanSuccess,
                    onScanError
                );
            } else {
                alert("No se detectaron cámaras.");
            }
        }).catch(err => {
            console.error(err);
            alert("Error al acceder a la cámara: " + err);
        });
    </script>
</body>
</html>
