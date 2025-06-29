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
            padding: 20px;
        }
        #reader {
            width: 100%;
            max-width: 400px;
            margin: auto;
        }
        .error {
            color: red;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h2>📷 Escaneo QR para Ingreso Profesor</h2>
    <div id="reader"></div>

    <form id="qrForm" action="registro_qr_multi.php" method="POST" style="display:none;">
        <input type="hidden" name="codigo_qr" id="codigo_qr">
    </form>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            document.getElementById("codigo_qr").value = decodedText;
            document.getElementById("qrForm").submit();
        }

        function onScanError(errorMessage) {
            // Opcional: mostrar errores de lectura
        }

        const html5QrcodeScanner = new Html5QrcodeScanner("reader", {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        });

        html5QrcodeScanner.render(onScanSuccess, onScanError);
    </script>
</body>
</html>
