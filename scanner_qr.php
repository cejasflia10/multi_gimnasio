<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Escaneo QR para Ingreso</title>
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      text-align: center;
      margin: 0;
      padding: 20px;
    }
    h1 {
      margin-top: 20px;
    }
    #qr-reader {
      width: 90%;
      max-width: 400px;
      margin: 20px auto;
    }
    #resultado {
      margin-top: 20px;
      font-size: 18px;
      color: #0f0;
    }
    #acciones {
      margin-top: 20px;
    }
    button {
      margin: 10px;
      padding: 10px 20px;
      font-size: 16px;
      border: none;
      border-radius: 10px;
      background-color: gold;
      color: black;
      cursor: pointer;
    }
  </style>
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body>
  <h1>📷 Escaneo QR para Ingreso</h1>
  <div id="qr-reader"></div>
  <div id="resultado"></div>
  <div id="acciones" style="display: none;">
    <button onclick="reanudarEscaneo()">Seguir Escaneando</button>
    <button onclick="cerrarEscaner()">Cerrar</button>
  </div>

  <script>
    let scanner = new Html5Qrcode("qr-reader");

    function iniciarEscaner() {
      Html5Qrcode.getCameras().then(cameras => {
        if (cameras && cameras.length) {
          scanner.start(
            { facingMode: "environment" },
            {
              fps: 10,
              qrbox: 250
            },
            qrCodeMessage => {
              document.getElementById("resultado").innerText = `QR detectado: ${qrCodeMessage}`;
              scanner.stop().then(() => {
                document.getElementById("acciones").style.display = "block";
              });
            },
            errorMessage => {}
          ).catch(err => {
            document.getElementById("resultado").innerText = `Error al iniciar: ${err}`;
          });
        }
      }).catch(err => {
        document.getElementById("resultado").innerText = `No se detectaron cámaras: ${err}`;
      });
    }

    function reanudarEscaneo() {
      document.getElementById("acciones").style.display = "none";
      document.getElementById("resultado").innerText = "";
      iniciarEscaner();
    }

    function cerrarEscaner() {
      document.getElementById("qr-reader").innerHTML = "";
      document.getElementById("resultado").innerText = "Escaneo finalizado.";
      document.getElementById("acciones").style.display = "none";
    }

    iniciarEscaner();
  </script>
</body>
</html>
