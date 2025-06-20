<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo de QR - Ingreso al Gimnasio</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: black;
      color: gold;
      font-family: Arial, sans-serif;
      text-align: center;
      margin: 0;
      padding: 0;
    }
    h1 {
      margin-top: 20px;
    }
    #video {
      width: 90%;
      max-width: 400px;
      margin: 20px auto;
      border: 4px solid gold;
      border-radius: 10px;
    }
    #resultado {
      margin-top: 20px;
      font-size: 18px;
    }
  </style>
</head>
<body>
  <h1>📸 Escaneo de QR - Ingreso al Gimnasio</h1>
  <video id="video" autoplay playsinline></video>
  <div id="resultado"></div>

  <script>
    const video = document.getElementById('video');
    const resultado = document.getElementById('resultado');
    let ultimoCodigo = "";

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(stream => {
        video.srcObject = stream;
      })
      .catch(err => {
        resultado.innerText = "Error al acceder a la cámara: " + err;
      });

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    function escanearQR() {
      if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);

        const code = jsQR(imageData.data, imageData.width, imageData.height, {
          inversionAttempts: "dontInvert",
        });

        if (code && code.data !== ultimoCodigo) {
          ultimoCodigo = code.data;
          resultado.innerText = "QR leído: " + code.data;
          fetch("verificar_asistencia.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "dni=" + encodeURIComponent(code.data)
          })
          .then(response => response.text())
          .then(texto => {
            resultado.innerText = texto;
            setTimeout(() => { ultimoCodigo = ""; resultado.innerText = ""; }, 3000);
          });
        }
      }
      requestAnimationFrame(escanearQR);
    }

    // Carga jsQR
    const script = document.createElement('script');
    script.src = "https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js";
    script.onload = () => { requestAnimationFrame(escanearQR); };
    document.body.appendChild(script);
  </script>
</body>
</html>
