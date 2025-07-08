<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$es_admin = ($_SESSION['rol'] ?? '') === 'admin';

if ($es_admin) {
    $sql = "SELECT p.*, g.nombre AS gimnasio_nombre
            FROM profesores p
            JOIN gimnasios g ON p.gimnasio_id = g.id
            ORDER BY p.apellido ASC";
} else {
    $sql = "SELECT p.*, g.nombre AS gimnasio_nombre
            FROM profesores p
            JOIN gimnasios g ON p.gimnasio_id = g.id
            WHERE p.gimnasio_id = $gimnasio_id
            ORDER BY p.apellido ASC";
}

$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Profesores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>

<body>
<div class="contenedor">

<h2>👨‍🏫 Listado de Profesores</h2>

<table>
    <tr>
        <th>Apellido</th>
        <th>Nombre</th>
        <th>DNI</th>
        <th>Teléfono</th>
        <th>Email</th>
        <th>Gimnasio</th>
        <th>Acciones</th>
    </tr>
    <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $row['apellido'] ?></td>
            <td><?= $row['nombre'] ?></td>
            <td><?= $row['dni'] ?></td>
            <td><?= $row['telefono'] ?></td>
            <td><?= $row['email'] ?></td>
            <td><?= $row['gimnasio_nombre'] ?></td>
            <td>
                <a class="boton" href="editar_profesor.php?id=<?= $row['id'] ?>">✏️ Editar</a>
                <a class="boton" href="eliminar_profesor.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar este profesor?')">🗑️ Eliminar</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>
</div>

</body>
</html>
