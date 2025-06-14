<?php
include 'conexion.php';

$categoria_id = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$condicion = $categoria_id ? "WHERE p.categoria = $categoria_id" : '';

$productos = $conexion->query("SELECT p.id, p.nombre, p.detalle, p.compra, p.venta, c.nombre AS categoria
                               FROM productos p
                               LEFT JOIN categorias c ON p.categoria = c.id
                               $condicion
                               ORDER BY p.nombre");

$categorias = $conexion->query("SELECT * FROM categorias ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Productos</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #333; }
        th { color: #ffc107; }
        .btn { padding: 5px 10px; margin: 0 5px; background: #ffc107; color: #111; border: none; border-radius: 5px; cursor: pointer; }
        select { padding: 5px; margin-top: 10px; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Productos</h1>

    <form method="GET" style="margin-bottom: 20px;">
        <label>Filtrar por categoría:</label>
        <select name="categoria" onchange="this.form.submit()">
            <option value="">-- Todas --</option>
            <?php while ($cat = $categorias->fetch_assoc()): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoria_id == $cat['id'] ? 'selected' : '' ?>><?= $cat['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
    </form>

    <table>
        <tr>
            <th>Nombre</th>
            <th>Detalle</th>
            <th>Compra</th>
            <th>Venta</th>
            <th>Categoría</th>
            <th>Acciones</th>
        </tr>
        <?php while ($producto = $productos->fetch_assoc()): ?>
        <tr>
            <td><?= $producto['nombre'] ?></td>
            <td><?= $producto['detalle'] ?></td>
            <td>$<?= number_format($producto['compra'], 2) ?></td>
            <td>$<?= number_format($producto['venta'], 2) ?></td>
            <td><?= $producto['categoria'] ?></td>
            <td>
                <button class="btn" onclick="location.href='editar_producto.php?id=<?= $producto['id'] ?>'">Editar</button>
                <button class="btn" onclick="if(confirm('¿Eliminar producto?')) location.href='eliminar_producto.php?id=<?= $producto['id'] ?>'">Eliminar</button>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
