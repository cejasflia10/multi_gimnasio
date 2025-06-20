<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING); // Oculta warnings y deprecated
include "phpqrcode/qrlib.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["dni"])) {
    $dni = trim($_POST["dni"]);
    $nombre = trim($_POST["nombre"]);
    $id = trim($_POST["id"]);

    $textoQR = $dni . "|" . $nombre . "|" . $id;

    // Ruta donde se guardará el QR
    $filename = "temp_qr/qr_" . $dni . ".png";
    if (!file_exists("temp_qr")) {
        mkdir("temp_qr");
    }

    QRcode::png($textoQR, $filename, QR_ECLEVEL_H, 6);
    echo "<h3 style='color: gold;'>QR generado correctamente</h3>";
    echo "<img src='$filename' alt='QR generado'><br><br>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Generar QR</title>
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 30px;
    }
    input, button {
      padding: 10px;
      margin: 5px;
      border: none;
      border-radius: 5px;
    }
    button {
      background-color: gold;
      color: black;
      cursor: pointer;
    }
    button:hover {
      background-color: orange;
    }
  </style>
</head>
<body>
  <h2>Generar código QR para cliente</h2>
  <form method="POST">
    <label>DNI:</label><br>
    <input type="text" name="dni" required><br>
    <label>Nombre y Apellido:</label><br>
    <input type="text" name="nombre" required><br>
    <label>ID del Cliente:</label><br>
    <input type="text" name="id" required><br>
    <button type="submit">Generar QR</button>
  </form>
</body>
</html>
