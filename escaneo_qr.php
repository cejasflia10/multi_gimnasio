<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escanear QR - Asistencia</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background-color: black;
      color: gold;
      text-align: center;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }
    #preview {
      width: 100%;
      max-width: 600px;
      margin: 20px auto;
      border: 3px solid gold;
      border-radius: 8px;
    }
  </style>
</head>
<body>
  <h2>📷 Escaneo de QR - Ingreso al Gimnasio</h2>
  <video id="preview"></video>

  <form id="formulario" method="POST" action="registrar_asistencia_qr.php">
    <input type="hidden" name="dni" id="dni">
  </form>

  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <script>
    function onScanSuccess(decodedText, decodedResult) {
      document.getElementById("dni").value = decodedText;
      document.getElementById("formulario").submit();
    }

    function startQRScanner() {
      const html5QrCode = new Html5Qrcode("preview");
      html5QrCode.start(
        { facingMode: "environment" },
        {
          fps: 10,
          qrbox: 250
        },
        onScanSuccess
      ).catch(err => {
        console.error("Error al iniciar cámara:", err);
      });
    }

    window.onload = startQRScanner;
  </script>
</body>
</html>
