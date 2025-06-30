<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo QR</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <style>
    body {
      background-color: black;
      color: gold;
      text-align: center;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    #reader {
      width: 100%;
      max-width: 400px;
      margin: auto;
    }
    #resultado {
      margin-top: 20px;
      font-size: 20px;
    }
  </style>
</head>
<body>
  <h1>Escaneo QR</h1>
  <div id="reader"></div>
  <div id="resultado"></div>

  <script>
    function iniciarEscaneo() {
      const scanner = new Html5Qrcode("reader");
      const config = { fps: 10, qrbox: 250 };

      scanner.start(
        { facingMode: "environment" },
        config,
        (decodedText, decodedResult) => {
          scanner.stop();

          let codigo = decodedText.trim();
          let tipo = codigo.charAt(0); // "P" o "C"
          let dni = codigo.substring(1);

          let endpoint = "registrar_asistencia_qr.php";
          let postData = "dni=" + encodeURIComponent(dni) + "&tipo=" + tipo;

          fetch(endpoint, {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded"
            },
            body: postData
          })
          .then(response => response.text())
          .then(data => {
            document.getElementById("resultado").innerHTML = data;
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
        },
        (errorMessage) => {
          // console.log(errorMessage);
        }
      );
    }

    iniciarEscaneo();
  </script>
</body>
</html>
