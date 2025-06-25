<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) die("Acceso denegado.");
$gimnasio_id = $_SESSION['gimnasio_id'];
$disciplinas = $conexion->query("SELECT * FROM disciplinas WHERE gimnasio_id = $gimnasio_id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Disciplinas</title>
    <style>
        body { background: #111; color: gold; font-family: sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid gold; padding: 10px; text-align: left; }
        a, button { color: gold; text-decoration: none; margin-right: 10px; }
    </style>
</head>
<body>
<h2>Disciplinas</h2>
<a href='crear_disciplina.php'>+ Agregar Disciplina</a>
<table>
    <tr><th>Nombre</th><th>Acciones</th></tr>
    <?php while($row = $disciplinas->fetch_assoc()): ?>
        <tr>
            <td><?= $row['nombre'] ?></td>
            <td>
                <a href="editar_disciplina.php?id=<?= $row['id'] ?>">Editar</a>
                <a href="eliminar_disciplina.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar esta disciplina?')">Eliminar</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
