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
  <h1>📸 Escaneo QR para Ingreso Profesor</h1>
  <div id="reader"></div>

  <script>
    function onScanSuccess(decodedText, decodedResult) {
      console.log("QR detectado:", decodedText); // Ver en consola
      alert("QR detectado: " + decodedText);     // Mostrar visual
      if (decodedText.startsWith('P')) {
        window.location.href = "registrar_asistencia_profesor.php?codigo=" + encodeURIComponent(decodedText);
      } else {
        alert("⚠️ El QR escaneado no es de profesor: " + decodedText);
      }
    }

    function onScanFailure(error) {
      // Solo para debug si querés: console.warn(`Escaneo fallido: ${error}`);
    }

    const html5QrCode = new Html5Qrcode("reader");
    const config = { fps: 10, qrbox: 250 };

    Html5Qrcode.getCameras().then(devices => {
      if (devices && devices.length) {
        html5QrCode.start(
          { facingMode: "environment" }, // Cámara trasera
          config,
          onScanSuccess,
          onScanFailure
        );
      } else {
        alert("No se detectó cámara disponible.");
      }
    }).catch(err => {
      alert("Error al inicializar cámara: " + err);
    });
  </script>
</body>
</html>
