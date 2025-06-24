
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Escanear QR - Registro de Asistencia</title>
  <script src="https://unpkg.com/html5-qrcode"></script>
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
    .mensaje {
      margin-top: 20px;
      font-size: 1.2em;
    }
    .logo {
      margin-top: 10px;
      margin-bottom: 20px;
    }
    .logo img {
      width: 120px;
    }
  </style>
</head>
<body>
  <div class="logo">
    <img src="logo.png" alt="Logo Gym" />
  </div>
  <h2>Escanear Código QR</h2>
  <div id="reader"></div>
  <div class="mensaje" id="mensaje"></div>

  <form id="formulario" action="registrar_asistencia_qr.php" method="POST">
    <input type="hidden" name="dni" id="dni" />
  </form>

  <script>
    function onScanSuccess(decodedText, decodedResult) {
      document.getElementById('dni').value = decodedText;
      document.getElementById('formulario').submit();
    }

    function onScanFailure(error) {
      // No mostrar errores repetitivos
    }

    const html5QrcodeScanner = new Html5QrcodeScanner("reader", {
      fps: 10,
      qrbox: 250
    });

    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
  </script>
</body>
</html>
