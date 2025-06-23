<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include 'menu.php';

$resultado = $conexion->query("SELECT * FROM usuarios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios del Sistema</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: gold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #333;
        }
        a.boton {
            background-color: gold;
            color: black;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-weight: bold;
            margin-right: 5px;
        }
        .acciones {
            white-space: nowrap;
        }
    </style>
</head>
<body>

<h2>Lista de Usuarios</h2>

<table>
    <tr>
        <th>ID</th>
        <th>Usuario</th>
        <th>Rol</th>
        <th>Gimnasio</th>
        <th>Acciones</th>
    </tr>
    <?php while($fila = $resultado->fetch_assoc()): ?>
    <tr>
        <td><?= $fila['id'] ?></td>
        <td><?= htmlspecialchars($fila['usuario'] ?? '', ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($fila['rol'] ?? '', ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($fila['id_gimnasio'] ?? '', ENT_QUOTES) ?></td>
        <td class="acciones">
            <a class="boton" href="editar_usuario.php?id=<?= $fila['id'] ?>">Editar</a>
            <a class="boton" href="eliminar_usuario.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar este usuario?')">Eliminar</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
