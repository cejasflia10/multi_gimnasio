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
      padding: 20px;
    }
    #reader {
      width: 320px;
      margin: auto;
      border: 4px solid gold;
      border-radius: 10px;
    }
    #resultado {
      margin-top: 20px;
      font-size: 20px;
    }
  </style>
</head>
<body>

  <h2>📸 Escaneo QR para Ingreso Profesor</h2>
  <div id="reader"></div>
  <div id="resultado"></div>

  <script>
    const scanner = new Html5Qrcode("reader");

    function iniciarEscaneo() {
      scanner.start(
        { facingMode: "environment" }, // usa cámara trasera
        {
          fps: 10,
          qrbox: { width: 250, height: 250 }
        },
        (decodedText) => {
          scanner.stop().then(() => {
            // Validar que comience con "P-"
            if (!decodedText.startsWith("P-")) {
              document.getElementById("resultado").innerHTML = "⚠️ QR no válido para profesor";
              setTimeout(() => {
                document.getElementById("resultado").innerHTML = "";
                iniciarEscaneo();
              }, 3000);
              return;
            }

            const dni = decodedText.replace("P-", "");

            // Enviar DNI al backend
            fetch("registrar_asistencia_profesor.php", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: "rfid=" + encodeURIComponent(dni)
            })
            .then(response => response.text())
            .then(data => {
              document.getElementById("resultado").innerHTML = data;
              setTimeout(() => {
                document.getElementById("resultado").innerHTML = "";
                iniciarEscaneo();
              }, 3000);
            })
            .catch(() => {
              document.getElementById("resultado").innerHTML = "❌ Error al registrar asistencia.";
              setTimeout(() => {
                document.getElementById("resultado").innerHTML = "";
                iniciarEscaneo();
              }, 3000);
            });
          });
        },
        (errorMessage) => {
          // Opcional: manejar errores de escaneo
        }
      ).catch(err => {
        document.getElementById("resultado").innerHTML = "❌ No se pudo iniciar la cámara.";
      });
    }

    iniciarEscaneo();
  </script>
</body>
</html>
