<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo QR para Ingreso Profesor</title>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <style>
    body {
      background-color: black;
      color: gold;
      font-family: Arial, sans-serif;
      text-align: center;
    }
    #reader {
      width: 320px;
      margin: auto;
      border: 4px solid gold;
      border-radius: 10px;
      margin-top: 20px;
    }
    #resultado {
      margin-top: 20px;
      font-size: 18px;
    }
  </style>
</head>
<body>

  <h2>📸 Escaneo QR para Ingreso Profesor</h2>
  <div id="reader"></div>
  <div id="resultado"></div>

  <script>
    const scanner = new Html5Qrcode("reader");

    function escanearQR() {
      scanner.start(
        { facingMode: "environment" },  // Cámara trasera
        {
          fps: 10,
          qrbox: { width: 250, height: 250 }
        },
        (qrCodeMessage) => {
          scanner.stop();

          if (!qrCodeMessage.startsWith("P-")) {
            document.getElementById("resultado").innerHTML = "⚠️ QR no válido para profesor.";
            setTimeout(() => location.reload(), 3000);
            return;
          }

          const dni = qrCodeMessage.replace("P-", "");

          fetch("registrar_asistencia_profesor.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "rfid=" + encodeURIComponent(dni)
          })
          .then(response => response.text())
          .then(data => {
            document.getElementById("resultado").innerHTML = data;
            setTimeout(() => location.reload(), 3000);
          })
          .catch(() => {
            document.getElementById("resultado").innerHTML = "❌ Error de conexión.";
            setTimeout(() => location.reload(), 3000);
          });
        },
        (errorMessage) => {
          // Error de escaneo (silenciado para evitar spam)
        }
      ).catch(err => {
        document.getElementById("resultado").innerHTML = "❌ No se pudo acceder a la cámara.";
      });
    }

    escanearQR();
  </script>

</body>
</html>
