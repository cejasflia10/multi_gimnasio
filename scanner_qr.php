
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR - Fight Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            margin-top: 20px;
            font-size: 28px;
        }

        #qr-reader {
            width: 90%;
            margin: 20px auto;
            max-width: 400px;
        }

        #manual-entry {
            margin-top: 20px;
        }

        input[type="text"] {
            padding: 10px;
            font-size: 16px;
            width: 80%;
            margin-bottom: 10px;
            border: 2px solid gold;
            border-radius: 5px;
        }

        button {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            font-size: 16px;
            margin: 10px 5px;
            border: none;
            border-radius: 5px;
        }

        #result {
            margin-top: 20px;
            font-size: 18px;
        }
    </style>
    <script src="https://unpkg.com/html5-qrcode@2.3.7/minified/html5-qrcode.min.js"></script>
</head>
<body>
    <h1>Escaneo QR - Fight Academy</h1>

    <div id="qr-reader"></div>

    <div id="manual-entry">
        <input type="text" id="dniInput" placeholder="Ingresar DNI manualmente" />
        <br>
        <button onclick="verificarDNI()">Verificar DNI</button>
    </div>

    <div id="result"></div>

    <script>
        function verificarDNI() {
            const dni = document.getElementById("dniInput").value;
            if (dni) {
                document.getElementById("result").innerHTML = "DNI ingresado: " + dni;
                // Aquí puedes redireccionar o enviar el DNI al backend
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            document.getElementById("result").innerHTML = "QR Detectado: " + decodedText;
            // Aquí puedes redireccionar o enviar el dato escaneado
        }

        function onScanError(errorMessage) {
            // Silencioso
        }

        const html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader", { fps: 10, qrbox: 250 });
        html5QrcodeScanner.render(onScanSuccess, onScanError);
    </script>
</body>
</html>
