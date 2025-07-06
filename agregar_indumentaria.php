<?php
include 'conexion.php';
include 'menu_horizontal.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $talle = $_POST['talle'];
    $precio_compra = $_POST['precio_compra'];
    $precio_venta = $_POST['precio_venta'];
    $stock = $_POST['stock'];

    $stmt = $conexion->prepare("INSERT INTO productos_indumentaria (nombre, talle, precio_compra, precio_venta, stock, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddii", $nombre, $talle, $precio_compra, $precio_venta, $stock, $gimnasio_id);
    if ($stmt->execute()) {
        $mensaje = "✅ Indumentaria registrada correctamente.";
    } else {
        $mensaje = "❌ Error al registrar: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <link rel="stylesheet" href="estilo_unificado.css">

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Indumentaria</title>
>
</head>
<script src="fullscreen.js"></script>

<body>

<div class="contenedor">
  <h2>Agregar Indumentaria</h2>
  <form method="POST">
    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <label>Talle:</label>
    <input type="text" name="talle">

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
