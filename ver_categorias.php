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
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #111;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        .acciones a {
            background-color: gold;
            color: black;
            padding: 6px 12px;
            text-decoration: none;
            margin: 0 5px;
            border-radius: 4px;
            display: inline-block;
        }
        .acciones a:hover {
            background-color: #e5c100;
        }
        .btn-top {
            display: inline-block;
            background-color: gold;
            color: black;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<?php if (isset($_GET['actualizado']) && $_GET['actualizado'] == 1): ?>
<div style="background-color: #0f0; color: black; padding: 10px; margin: 10px 0; text-align: center; font-weight: bold; border-radius: 6px;">
    ✅ Categoría actualizada correctamente
</div>
<?php endif; ?>

<h1 style="text-align:center;">📂 Categorías de Productos</h1>

<div style="text-align:center; margin-bottom: 20px;">
    <a class="btn-top" href="agregar_categoria.php">➕ Agregar Nueva Categoría</a>
    <a class="btn-top" href="index.php">🏠 Volver al Menú</a>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($cat = $categorias->fetch_assoc()): ?>
        <tr>
            <td><?= $cat['id'] ?></td>
            <td><?= htmlspecialchars($cat['nombre']) ?></td>
            <td class="acciones">
                <a href="editar_categoria.php?id=<?= $cat['id'] ?>">✏️ Editar</a>
                <a href="eliminar_categoria.php?id=<?= $cat['id'] ?>" onclick="return confirm('¿Eliminar esta categoría?')">🗑️ Eliminar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
