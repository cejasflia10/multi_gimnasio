<?php
// Ocultar warnings deprecados
error_reporting(E_ERROR | E_PARSE);
require 'phpqrcode/qrlib.php';

$dni = $_GET['dni'] ?? 'SIN_DNI';

ob_start();
QRcode::png($dni, null, QR_ECLEVEL_H, 10); // Nivel alto y tamaño grande
$imageData = base64_encode(ob_get_contents());
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>QR generado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
      text-align: center;
    }
    h1 {
      color: #f7d774;
      margin-bottom: 20px;
    }
    .qr {
      background-color: #fff;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 20px #000;
      margin-bottom: 30px;
    }
    .qr img {
      width: 280px;
      height: 280px;
    }
    a {
      color: #f7d774;
      font-size: 18px;
      text-decoration: none;
      border: 1px solid #f7d774;
      padding: 10px 20px;
      border-radius: 5px;
      transition: 0.3s;
    }
    a:hover {
      background-color: #f7d774;
      color: #111;
    }
  </style>
</head>
<body>

  <h1>QR generado</h1>

  <div class="qr">
    <img src="data:image/png;base64,<?= $imageData ?>" alt="QR generado">
  </div>

  <a href="formulario_qr.php">Volver</a>

</body>
</html>
