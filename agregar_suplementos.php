<?php
include 'conexion.php';
include 'menu_horizontal.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $tipo = $_POST['tipo'];
    $precio_compra = $_POST['precio_compra'];
    $precio_venta = $_POST['precio_venta'];
    $stock = $_POST['stock'];

    $stmt = $conexion->prepare("INSERT INTO productos (nombre, precio) VALUES (?, ?)");
    $stmt->bind_param("ssddii", $nombre, $tipo, $precio_compra, $precio_venta, $stock, $gimnasio_id);
    if ($stmt->execute()) {
        $mensaje = "✅ Suplemento registrado correctamente.";
    } else {
        $mensaje = "❌ Error al registrar: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Suplemento</title>
  <style>
    body {
      background: #000;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 600px;
      margin: 30px auto;
      background-color: #111;
      padding: 20px;
      border-radius: 10px;
    }

    h2 {
      text-align: center;
      color: #ffc107;
    }

    label {
      display: block;
      margin-top: 15px;
      font-weight: bold;
    }

    input {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border-radius: 5px;
      border: none;
    }

    .btn {
      margin-top: 20px;
      width: 100%;
      padding: 10px;
      background: #ffc107;
      color: #000;
      border: none;
      border-radius: 5px;
      font-weight: bold;
      cursor: pointer;
    }

    .mensaje {
      margin-top: 20px;
      text-align: center;
      font-weight: bold;
    }

    @media (max-width: 768px) {
      .container {
        margin: 20px;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <h2>Agregar Suplemento</h2>
  <form method="POST">
    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <label>Tipo:</label>
    <input type="text" name="tipo" required>

    <label>Precio de Compra:</label>
    <input type="number" step="0.01" name="precio_compra" required>

    <label>Precio de Venta:</label>
    <input type="number" step="0.01" name="precio_venta" required>

    <label>Stock:</label>
    <input type="number" name="stock" required>

    <button type="submit" class="btn">Agregar</button>
  </form>

  <?php if (!empty($mensaje)): ?>
    <div class="mensaje"><?= $mensaje ?></div>
  <?php endif; ?>
</div>

</body>
</html>
