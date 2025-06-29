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
    h1 {
      color: gold;
    }
    #reader {
      width: 300px;
      margin: 0 auto;
      border: 5px solid gold;
      border-radius: 10px;
    }
  </style>
</head>
<body>
  <h1>📸 Escaneo QR para Ingreso / Egreso Profesor</h1>
  <div id="reader"></div>

  <form id="form-envio" action="registrar_asistencia_profesor.php" method="POST" style="display:none;">
    <input type="hidden" name="codigo" id="codigo">
  </form>

  <script>
    function onScanSuccess(decodedText, decodedResult) {
      if (decodedText.startsWith('P')) {
        document.getElementById("codigo").value = decodedText;
        document.getElementById("form-envio").submit();
      } else {
        alert("⚠️ El QR no es válido para profesor.");
      }
    }

    function onScanFailure(error) {
      // Silencio
    }

    const html5QrCode = new Html5Qrcode("reader");
    const config = { fps: 10, qrbox: 250 };

    Html5Qrcode.getCameras().then(devices => {
      if (devices && devices.length) {
        html5QrCode.start(
          { facingMode: "environment" },
          config,
          onScanSuccess,
          onScanFailure
        );
      } else {
        alert("No se detectó cámara.");
      }
    }).catch(err => {
      alert("Error al acceder a la cámara: " + err);
    });
  </script>
</body>
</html>
