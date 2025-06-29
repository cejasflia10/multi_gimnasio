<!-- scanner_qr.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escanear QR para Ingreso</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { background: black; color: gold; font-family: Arial; text-align: center; padding: 20px; }
        #reader { width: 100%; max-width: 400px; margin: auto; }
    </style>
</head>
<body>
    <h2>📷 Escaneo QR para Ingreso</h2>
    <div id="reader"></div>
    <script>
        function onScanSuccess(decodedText, decodedResult) {
            // Enviar el QR escaneado automáticamente
            fetch("registro_qr_multi.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "codigo=" + encodeURIComponent(decodedText)
            })
            .then(res => res.text())
            .then(data => {
                document.body.innerHTML = data; // Mostrar la respuesta del servidor
            });
            html5QrcodeScanner.clear();
        }

        const html5QrcodeScanner = new Html5QrcodeScanner("reader", {
            fps: 10,
            qrbox: 250
        });
        html5QrcodeScanner.render(onScanSuccess);
    </script>
</body>
</html>
