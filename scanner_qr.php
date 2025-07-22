<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo QR o DNI para Ingreso</title>
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
    input[type="text"] {
      padding: 10px;
      font-size: 18px;
      width: 250px;
      margin-top: 20px;
    }
    button {
      padding: 10px 20px;
      font-size: 16px;
      margin-top: 10px;
      background-color: gold;
      border: none;
      cursor: pointer;
    }
  </style>
</head>
<body>

  <h2>📷 Escaneo QR o DNI</h2>

  <div id="reader"></div>

  <form onsubmit="enviarDNIManual(event)">
    <input type="text" id="dni_manual" placeholder="Ingresar DNI manual" required pattern="\d+">
    <br>
    <button type="submit">Registrar Ingreso</button>
  </form>

  <div id="resultado"></div>

  <script>
    const scanner = new Html5Qrcode("reader");

    function registrarAsistencia(dni) {
      fetch("registrar_asistencia_qr.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "dni=" + encodeURIComponent(dni)
      })
      .then(response => response.text())
      .then(data => {
        document.getElementById("resultado").innerHTML = data;
        setTimeout(() => {
          document.getElementById("resultado").innerHTML = "";
          iniciarEscaneo();
        }, 4000);
      })
      .catch(() => {
        document.getElementById("resultado").innerHTML = "<span style='color:red;'>❌ Error al registrar asistencia.</span>";
        setTimeout(() => {
          document.getElementById("resultado").innerHTML = "";
          iniciarEscaneo();
        }, 4000);
      });
    }

    function iniciarEscaneo() {
      scanner.start(
        { facingMode: "environment" },
        {
          fps: 10,
          qrbox: { width: 250, height: 250 }
        },
        (decodedText) => {
          if (/^\d+$/.test(decodedText)) {
            scanner.stop().then(() => {
              registrarAsistencia(decodedText);
            });
          } else {
            document.getElementById("resultado").innerHTML = "<span style='color:red;'>❌ Código inválido. Debe ser solo números.</span>";
            setTimeout(() => {
              document.getElementById("resultado").innerHTML = "";
              iniciarEscaneo();
            }, 3000);
          }
        },
        errorMessage => {
          // Silencioso
        }
      ).catch(err => {
        document.getElementById("resultado").innerHTML = "<span style='color:red;'>❌ Error al acceder a la cámara</span>";
      });
    }

    function enviarDNIManual(e) {
      e.preventDefault();
      const dni = document.getElementById("dni_manual").value.trim();
      if (/^\d+$/.test(dni)) {
        registrarAsistencia(dni);
        document.getElementById("dni_manual").value = "";
      } else {
        document.getElementById("resultado").innerHTML = "<span style='color:red;'>❌ El DNI debe contener solo números.</span>";
      }
    }

    window.onload = iniciarEscaneo;
  </script>

</body>
</html>
