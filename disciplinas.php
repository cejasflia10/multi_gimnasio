<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'];

$resultado = $conexion->query("SELECT id, nombre FROM disciplinas WHERE (gimnasio_id = $gimnasio_id OR id_gimnasio = $gimnasio_id)");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Disciplinas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        h2 {
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid gold;
            padding: 12px;
            text-align: center;
        }

        a {
            color: gold;
            text-decoration: none;
            font-weight: bold;
        }

        .nuevo {
            margin: 20px 0;
            display: inline-block;
        }
    </style>
</head>
<body>
    <h2>Disciplinas</h2>
    <a class="nuevo" href="crear_disciplina.php">+ Nueva Disciplina</a>
    <table>
        <tr>
            <th>Nombre</th>
            <th>Acciones</th>
        </tr>
        <?php while ($fila = $resultado->fetch_assoc()) : ?>
            <tr>
                <td><?= htmlspecialchars($fila['nombre']) ?></td>
                <td>
                    <a href="editar_disciplina.php?id=<?= $fila['id'] ?>">Editar</a> |
                    <a href="eliminar_disciplina.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Eliminar esta disciplina?')">Eliminar</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
