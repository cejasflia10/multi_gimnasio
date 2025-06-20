
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escanear QR - Asistencia</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      text-align: center;
      margin: 0;
      padding: 0;
    }
    h1 {
      color: gold;
      margin: 20px 0;
    }
    #reader {
      width: 90%;
      margin: 0 auto;
      padding: 10px;
    }
    #result {
      margin-top: 20px;
      font-size: 18px;
    }
    #result.success {
      color: lightgreen;
    }
    #result.error {
      color: red;
    }
  </style>
  <script src="https://unpkg.com/html5-qrcode@2.3.10/minified/html5-qrcode.min.js"></script>
</head>
<body>
  <h1>Escaneo QR - Fight Academy</h1>
  <div id="reader"></div>
  <div id="result"></div>

  <script>
    function onScanSuccess(decodedText, decodedResult) {
      document.getElementById("result").innerHTML = "Verificando...";
      html5QrcodeScanner.clear().then(() => {
        fetch("verificar_qr.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "dni=" + encodeURIComponent(decodedText)
        })
        .then(response => response.text())
        .then(data => {
          document.getElementById("result").innerHTML = data;
          if (data.includes("Ingreso registrado")) {
            document.getElementById("result").className = "success";
          } else {
            document.getElementById("result").className = "error";
          }
          setTimeout(() => location.reload(), 4000);
        })
        .catch(error => {
          document.getElementById("result").innerHTML = "Error al conectar con el servidor.";
          document.getElementById("result").className = "error";
        });
      });
    }

    function onScanFailure(error) {
      // Silencio los errores normales
    }

    let html5QrcodeScanner = new Html5Qrcode("reader");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };

    Html5Qrcode.getCameras().then(devices => {
      if (devices && devices.length) {
        let cameraId = devices[0].id;
        html5QrcodeScanner.start(cameraId, config, onScanSuccess, onScanFailure);
      } else {
        document.getElementById("result").innerHTML = "No se encontraron cámaras.";
        document.getElementById("result").className = "error";
      }
    }).catch(err => {
      document.getElementById("result").innerHTML = "Error al acceder a la cámara.";
      document.getElementById("result").className = "error";
    });
  </script>
</body>
</html>
