<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo QR Profesor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <style>
    body {
      background-color: #000;
      color: gold;
      font-family: Arial;
      text-align: center;
      padding: 20px;
    }
    #reader {
      width: 300px;
      margin: auto;
      border: 3px solid gold;
      border-radius: 10px;
    }
  </style>
</head>
<body>
  <h1>📸 QR Profesor</h1>
  <div id="reader"></div>

  <form id="envio" method="POST" action="registrar_asistencia_profesor.php" style="display:none;">
    <input type="hidden" name="codigo" id="codigo">
  </form>

  <script>
    function onScanSuccess(decodedText) {
      if (decodedText.startsWith('P')) {
        document.getElementById('codigo').value = decodedText;
        document.getElementById('envio').submit();
      } else {
        alert("QR no válido (debe comenzar con P).");
      }
    }

    const html5QrCode = new Html5Qrcode("reader");
    html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess);
  </script>
</body>
</html>
