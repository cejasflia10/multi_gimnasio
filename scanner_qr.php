<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo QR para Ingreso</title>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <style>
    body {
      background-color: black;
      color: gold;
      font-family: Arial, sans-serif;
      text-align: center;
      padding-top: 20px;
    }
    #reader {
      width: 300px;
      margin: auto;
      border: 2px solid gold;
    }
  </style>
</head>
<body>

  <h2>📷 Escaneo QR para Ingreso</h2>
  <div id="reader"></div>

  <form id="formulario" method="POST" action="registrar_asistencia_qr.php">
    <input type="hidden" name="dni" id="dni">
  </form>

  <script>
    function onScanSuccess(decodedText, decodedResult) {
      // Detener escaneo
      html5QrcodeScanner.clear().then(_ => {
        // Enviar el DNI al backend
        document.getElementById("dni").value = decodedText;
        document.getElementById("formulario").submit();
      }).catch(error => {
        console.error("Error al detener escáner: ", error);
      });
    }

    const html5QrcodeScanner = new Html5QrcodeScanner(
      "reader", 
      { fps: 10, qrbox: 250 },
      /* verbose= */ false
    );
    html5QrcodeScanner.render(onScanSuccess);
  </script>

</body>
</html>
