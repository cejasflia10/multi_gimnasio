<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$categorias = $conexion->query("SELECT * FROM categorias WHERE gimnasio_id = $gimnasio_id ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        a, button {
            background: gold;
            color: black;
            text-decoration: none;
            padding: 6px 12px;
            margin: 5px;
            display: inline-block;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>
<?php if (isset($_GET['actualizado']) && $_GET['actualizado'] == 1): ?>
<div style="background-color: #0f0; color: black; padding: 10px; margin: 10px 0; text-align: center; font-weight: bold; border-radius: 6px;">
    ✅ Categoría actualizada correctamente
</div>
<?php endif; ?>

<h1>Categorías de Productos</h1>
<a href="agregar_categoria.php">Agregar Nueva Categoría</a>
<a href="index.php">Volver al Menú</a>
<table>
    <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Acciones</th>
    <td><a href="editar_categoria.php?id=<?= $fila['id'] ?>" style="background-color: #555; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none;">Editar</a></td></tr>
    <?php while ($cat = $categorias->fetch_assoc()): ?>
    <tr>
        <td><?= $cat['id'] ?></td>
        <td><?= $cat['nombre'] ?></td>
        <td>
            <a href="eliminar_categoria.php?id=<?= $cat['id'] ?>" onclick="return confirm('¿Eliminar esta categoría?')">Eliminar</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
