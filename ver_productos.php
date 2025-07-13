<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$resultado = $conexion->query("
    SELECT * FROM productos 
    WHERE gimnasio_id = $gimnasio_id 
    ORDER BY categoria, nombre
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Productos</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>📦 Productos</h2>
    <a href="agregar_producto.php" class="boton-verde">➕ Nuevo Producto</a>
    <br><br>
    <table class="tabla">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Talle/Oz</th>
                <th>Compra</th>
                <th>Venta</th>
                <th>Stock</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['nombre']) ?></td>
                <td><?= ucfirst($row['categoria']) ?></td>
                <td><?= $row['talle_oz'] ?></td>
                <td>$<?= number_format($row['precio_compra'], 2) ?></td>
                <td>$<?= number_format($row['precio_venta'], 2) ?></td>
                <td><?= $row['stock'] ?></td>
                <td>
                    <a href="editar_producto.php?id=<?= $row['id'] ?>" class="boton-naranja">✏️</a>
                    <a href="eliminar_producto.php?id=<?= $row['id'] ?>" class="boton-rojo" onclick="return confirm('¿Eliminar este producto?')">❌</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
