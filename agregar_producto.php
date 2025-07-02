<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $detalle = $_POST['detalle'];
    $compra = $_POST['compra'];
    $venta = $_POST['venta'];
    $categoria = $_POST['categoria'];
    $stock = $_POST['stock'];
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    $stmt = $conexion->prepare("INSERT INTO productos (nombre, detalle, compra, venta, categoria, stock, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddssi", $nombre, $detalle, $compra, $venta, $categoria, $stock, $gimnasio_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $mensaje = "✅ Producto registrado correctamente.";
    } else {
        $mensaje = "❌ Error al registrar producto.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        input, select, button { padding: 8px; margin: 6px 0; width: 100%; }
        label { font-weight: bold; display: block; margin-top: 10px; }
        .container { max-width: 500px; margin: auto; }
    </style>
</head>
<body>
<div class="container">
    <h2>📦 Agregar Producto</h2>

    <?php if ($mensaje): ?>
        <p><?= $mensaje ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="nombre">Nombre del producto:</label>
        <input type="text" name="nombre" required>

        <label for="detalle">Detalle:</label>
        <input type="text" name="detalle">

        <label for="compra">Precio de Compra:</label>
        <input type="number" name="compra" step="0.01" required>

        <label for="venta">Precio de Venta:</label>
        <input type="number" name="venta" step="0.01" required>

        <label for="stock">Stock inicial:</label>
        <input type="number" name="stock" min="0" required>

        <label for="categoria">Categoría:</label>
        <select name="categoria" required>
            <option value="Protección">Protección</option>
            <option value="Indumentaria">Indumentaria</option>
            <option value="Suplemento">Suplemento</option>
        </select>

        <br><br>
        <button type="submit">Registrar Producto</button>
    </form>
</div>
</body>
</html>
