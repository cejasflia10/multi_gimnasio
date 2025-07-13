<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $categoria = $_POST['categoria'];
    $talle_oz = trim($_POST['talle_oz']);
    $precio_compra = floatval($_POST['precio_compra']);
    $precio_venta = floatval($_POST['precio_venta']);
    $stock = intval($_POST['stock']);

    if ($nombre && $categoria && $precio_venta >= 0) {
        $stmt = $conexion->prepare("INSERT INTO productos (nombre, categoria, talle_oz, precio_compra, precio_venta, stock, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddii", $nombre, $categoria, $talle_oz, $precio_compra, $precio_venta, $stock, $gimnasio_id);
        if ($stmt->execute()) {
            $mensaje = "✅ Producto agregado correctamente.";
        } else {
            $mensaje = "❌ Error al guardar.";
        }
    } else {
        $mensaje = "❌ Campos incompletos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>➕ Agregar Producto</h2>
    <?php if ($mensaje): ?>
        <p class="mensaje"><?= $mensaje ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Nombre del producto:</label>
        <input type="text" name="nombre" required>

        <label>Categoría:</label>
        <select name="categoria" required>
            <option value="">-- Seleccionar --</option>
            <option value="proteccion">🥊 Protección</option>
            <option value="indumentaria">👕 Indumentaria</option>
            <option value="suplemento">🧃 Suplemento</option>
        </select>

        <label>Talle / OZ (opcional):</label>
        <input type="text" name="talle_oz">

        <label>Precio de compra:</label>
        <input type="number" step="0.01" name="precio_compra">

        <label>Precio de venta:</label>
        <input type="number" step="0.01" name="precio_venta" required>

        <label>Stock inicial:</label>
        <input type="number" name="stock" value="0" min="0">

        <br><br>
        <button type="submit">Guardar Producto</button>
        <a href="index.php" class="boton-volver">Volver al menú</a>
    </form>
</div>
</body>
</html>
