
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar Asistencia</title>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js"></script>
  <style>
    body {
      background-color: #000;
      color: #FFD700;
      font-family: Arial, sans-serif;
      text-align: center;
      padding: 20px;
    }
    h1 {
      margin-bottom: 20px;
    }
    #logo {
      width: 100px;
      margin-bottom: 10px;
    }
    button {
      background-color: #FFD700;
      color: #000;
      border: none;
      padding: 10px 20px;
      font-size: 18px;
      margin: 10px;
      cursor: pointer;
    }
    #reader {
      width: 90%;
      max-width: 400px;
      margin: 0 auto;
      display: none;
    }
    #manual-input {
      display: none;
      margin-top: 20px;
    }
    input[type="text"] {
      padding: 10px;
      font-size: 16px;
      width: 200px;
    }
    #result {
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <img id="logo" src="logo.png" alt="Logo">
  <h1>Registrar Asistencia</h1>

  <button onclick="usarQR()">📲 Escanear QR</button>
  <button onclick="usarDNI()">📝 Ingresar DNI</button>

  <div id="reader"></div>

  <div id="manual-input">
    <form method="GET" action="registrar_asistencia_qr.php">
      <input type="text" name="dni" placeholder="Ingresar DNI" required>
      <button type="submit">Registrar</button>
    </form>
  </div>

  <div id="result"></div>

  <script>
    function usarQR() {
      document.getElementById('reader').style.display = 'block';
      document.getElementById('manual-input').style.display = 'none';

      const html5QrCode = new Html5Qrcode("reader");
      html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        (qrCodeMessage) => {
          window.location.href = `registrar_asistencia_qr.php?dni=${encodeURIComponent(qrCodeMessage)}`;
        },
        (errorMessage) => {
          // Ignorar errores
        }
      );
    }

    function usarDNI() {
      document.getElementById('reader').style.display = 'none';
      document.getElementById('manual-input').style.display = 'block';
    }
  </script>
</body>
</html>
