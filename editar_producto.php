<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = intval($_GET['id'] ?? 0);
$mensaje = '';

$producto = $conexion->query("SELECT * FROM productos WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();

if (!$producto) {
    echo "<div class='contenedor'><p>Producto no encontrado.</p><a href='ver_productos.php'>Volver</a></div>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $categoria = $_POST['categoria'];
    $talle_oz = trim($_POST['talle_oz']);
    $precio_compra = floatval($_POST['precio_compra']);
    $precio_venta = floatval($_POST['precio_venta']);
    $stock = intval($_POST['stock']);

    if ($nombre && $categoria) {
        $stmt = $conexion->prepare("UPDATE productos SET nombre=?, categoria=?, talle_oz=?, precio_compra=?, precio_venta=?, stock=? WHERE id=? AND gimnasio_id=?");
        $stmt->bind_param("sssddiii", $nombre, $categoria, $talle_oz, $precio_compra, $precio_venta, $stock, $id, $gimnasio_id);
        if ($stmt->execute()) {
            $mensaje = "✅ Producto actualizado correctamente.";
        } else {
            $mensaje = "❌ Error al actualizar.";
        }
    } else {
        $mensaje = "❌ Campos obligatorios incompletos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>✏️ Editar Producto</h2>
    <?php if ($mensaje): ?>
        <p class="mensaje"><?= $mensaje ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Nombre del producto:</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required>

        <label>Categoría:</label>
        <select name="categoria" required>
            <option value="proteccion" <?= $producto['categoria'] == 'proteccion' ? 'selected' : '' ?>>Protección</option>
            <option value="indumentaria" <?= $producto['categoria'] == 'indumentaria' ? 'selected' : '' ?>>Indumentaria</option>
            <option value="suplemento" <?= $producto['categoria'] == 'suplemento' ? 'selected' : '' ?>>Suplemento</option>
        </select>

        <label>Talle / OZ:</label>
        <input type="text" name="talle_oz" value="<?= htmlspecialchars($producto['talle_oz']) ?>">

        <label>Precio de compra:</label>
        <input type="number" step="0.01" name="precio_compra" value="<?= $producto['precio_compra'] ?>">

        <label>Precio de venta:</label>
        <input type="number" step="0.01" name="precio_venta" value="<?= $producto['precio_venta'] ?>" required>

        <label>Stock:</label>
        <input type="number" name="stock" value="<?= $producto['stock'] ?>" min="0">

        <br><br>
        <button type="submit">Guardar Cambios</button>
        <a href="ver_productos.php" class="boton-volver">Volver</a>
    </form>
</div>
</body>
</html>
