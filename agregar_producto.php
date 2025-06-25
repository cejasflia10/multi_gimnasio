<?php
include 'conexion.php';
include 'menu_horizontal.php';

// Agregar nuevo producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    $nombre = $_POST['nombre'];
    $detalle = $_POST['detalle'];
    $compra = $_POST['compra'];
    $venta = $_POST['venta'];
    $categoria = $_POST['categoria'];

    $stmt = $conexion->prepare("INSERT INTO productos (nombre, detalle, compra, venta, categoria) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdds", $nombre, $detalle, $compra, $venta, $categoria);
    $stmt->execute();

    $mensaje = "Producto registrado exitosamente.";
}

// Obtener categorías de productos
$categorias = $conexion->query("SELECT * FROM categorias ORDER BY nombre");

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; border-radius: 4px; border: none; }
        .btn { margin-top: 15px; padding: 10px 20px; background: #ffc107; color: #111; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #e0a800; }
        .mensaje { margin-top: 20px; color: #0f0; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Agregar Producto</h1>
    <form method="POST">
        <label>Nombre del Producto:</label>
        <input type="text" name="nombre" required>

        <label>Detalle:</label>
        <input type="text" name="detalle" required>

        <label>Precio de Compra:</label>
        <input type="number" step="0.01" name="compra" required>

        <label>Precio de Venta:</label>
        <input type="number" step="0.01" name="venta" required>

        <label>Categoría:</label>
        <select name="categoria" required>
            <option value="">-- Seleccionar Categoría --</option>
            <?php while ($categoria = $categorias->fetch_assoc()): ?>
                <option value="<?= $categoria['id'] ?>"><?= $categoria['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <button type="submit" class="btn">Registrar Producto</button>
    </form>

    <?php if (isset($mensaje)): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>
</div>
</body>
</html>
