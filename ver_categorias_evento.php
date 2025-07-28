<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_eventos.php';

$resultado = $conexion->query("SELECT * FROM categorias_evento ORDER BY peso_min ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Categorías de Evento</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
        <h2>📋 Categorías de Competencia</h2>
        <a href="agregar_categoria_evento.php" class="btn-principal">➕ Agregar Categoría</a>
        <table>
            <tr>
                <th>Nombre</th>
                <th>Peso Min</th>
                <th>Peso Max</th>
                <th>Género</th>
                <th>Edad</th>
                <th>Acciones</th>
            </tr>
            <?php while ($row = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row['nombre'] ?></td>
                    <td><?= $row['peso_min'] ?> kg</td>
                    <td><?= $row['peso_max'] ?> kg</td>
                    <td><?= ucfirst($row['genero']) ?></td>
                    <td><?= $row['edad_min'] ?> - <?= $row['edad_max'] ?></td>
                    <td>
                        <a href="editar_categoria.php?id=<?= $row['id'] ?>">✏️</a>
                        <a href="eliminar_categoria.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar esta categoría?')">🗑️</a>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
</body>
</html>
