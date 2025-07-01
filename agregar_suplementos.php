<?php
include 'conexion.php';
include 'menu_horizontal.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$categorias = $conexion->query("SELECT id, nombre FROM categorias WHERE gimnasio_id = $gimnasio_id");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $tipo = $_POST['tipo'];
    $precio_compra = $_POST['precio_compra'];
    $precio_venta = $_POST['precio_venta'];
    $stock = $_POST['stock'];
    $categoria_id = $_POST['categoria_id'];

    $stmt = $conexion->prepare("INSERT INTO suplementos (nombre, tipo, precio_compra, precio_venta, stock, categoria_id, gimnasio_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdddii", $nombre, $tipo, $precio_compra, $precio_venta, $stock, $categoria_id, $gimnasio_id);
    $stmt->execute();
    header("Location: ver_suplementos.php");
    exit;
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
      background-color: #000;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    .container {
      max-width: 600px;
      margin: auto;
    }
    label, input, select, button {
      display: block;
      width: 100%;
      margin-top: 10px;
      padding: 10px;
      font-size: 16px;
      border-radius: 6px;
    }
    input, select {
      background-color: #111;
      color: gold;
      border: 1px solid gold;
    }
    button {
      background-color: gold;
      color: black;
      border: none;
      margin-top: 20px;
      font-weight: bold;
      cursor: pointer;
    }
    h1 {
      text-align: center;
    }
  </style>
</head>
<script src="fullscreen.js"></script>

<body>
  <div class="container">
    <h1>Agregar Suplemento</h1>
    <form method="POST">
      <label>Nombre:</label>
      <input type="text" name="nombre" required>

      <label>Tipo:</label>
      <input type="text" name="tipo" placeholder="Proteína, Creatina, etc.">

      <label>Precio de Compra:</label>
      <input type="number" step="0.01" name="precio_compra">

      <label>Precio de Venta:</label>
      <input type="number" step="0.01" name="precio_venta">

      <label>Stock:</label>
      <input type="number" name="stock">

      <label>Categoría:</label>
      <select name="categoria_id" required>
        <option value="">Seleccionar categoría</option>
        <?php while($c = $categorias->fetch_assoc()): ?>
          <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
        <?php endwhile; ?>
      </select>

      <button type="submit">Guardar Suplemento</button>
    </form>
  </div>
</body>
</html>
