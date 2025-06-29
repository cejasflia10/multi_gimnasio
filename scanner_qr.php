<!-- scanner_qr.php - escanea QR en vivo para registrar ingreso de profesor -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR Profesor</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        h2 {
            margin-bottom: 20px;
        }
        #reader {
            width: 100%;
            max-width: 400px;
            margin: auto;
        }
        .mensaje {
            margin-top: 20px;
            font-size: 18px;
            color: gold;
        }
    </style>
</head>
<body>
    <h2>📷 Escaneo QR para Ingreso Profesor</h2>
    <div id="reader"></div>
    <div class="mensaje" id="mensaje"></div>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            // Desactivar escaneo
            html5QrcodeScanner.clear();

            // Mostrar el valor escaneado
            document.getElementById("mensaje").innerText = "Registrando...";

            // Enviar el QR escaneado al backend
            fetch("registro_qr_multi.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "codigo=" + encodeURIComponent(decodedText)
            })
            .then(res => res.text())
            .then(data => {
                document.getElementById("mensaje").innerHTML = data;
            })
            .catch(err => {
                document.getElementById("mensaje").innerText = "Error al registrar: " + err;
            });
        }

        const html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", { fps: 10, qrbox: 250 }, /* verbose= */ false);
        html5QrcodeScanner.render(onScanSuccess);
    </script>
</body>
</html>
