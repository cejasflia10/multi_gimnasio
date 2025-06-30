<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo QR para Ingreso</title>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <style>
    body {
      background-color: black;
      color: gold;
      font-family: Arial, sans-serif;
      text-align: center;
      padding-top: 20px;
    }
    #reader {
      width: 300px;
      margin: auto;
      border: 2px solid gold;
    }
    #resultado {
      margin-top: 20px;
      font-size: 18px;
    }
  </style>
</head>
<body>

  <h2>📷 Escaneo QR para Ingreso</h2>
  <div id="reader"></div>
  <div id="resultado"></div>

  <script>
    const scanner = new Html5Qrcode("reader");

    function iniciarEscaneo() {
      scanner.start(
        { facingMode: "environment" },
        {
          fps: 10,
          qrbox: { width: 250, height: 250 }
        },
        (decodedText, decodedResult) => {
          scanner.stop().then(() => {
            // Enviar DNI al backend
            fetch("registrar_asistencia_qr.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded"
              },
              body: "dni=" + encodeURIComponent(decodedText)
            })
            .then(response => response.text())
            .then(data => {
              document.getElementById("resultado").innerHTML = data;

              // Reiniciar escaneo después de 4 segundos
              setTimeout(() => {
                document.getElementById("resultado").innerHTML = "";
                iniciarEscaneo();
              }, 4000);
            })
            .catch(error => {
              document.getElementById("resultado").innerHTML = "<span style='color:red;'>❌ Error al registrar asistencia.</span>";
              setTimeout(() => {
                document.getElementById("resultado").innerHTML = "";
                iniciarEscaneo();
              }, 4000);
            });
          });
        },
        errorMessage => {
          // Errores de lectura ignorados
        }
      ).catch(err => {
        document.getElementById("resultado").innerHTML = "<span style='color:red;'>❌ Error al acceder a la cámara</span>";
      });
    }

    // Iniciar al cargar
    window.onload = iniciarEscaneo;
  </script>

</body>
</html>
