<?php
session_start();
include 'conexion.php';

$resultado = $conexion->query("SELECT * FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Gimnasios</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 40px;
        }

        table {
            margin: 20px auto;
            border-collapse: collapse;
            width: 90%;
            background-color: #111;
            color: #fff;
        }

        th, td {
            border: 1px solid gold;
            padding: 10px;
        }

        th {
            background-color: #222;
        }

        .btn {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            margin-bottom: 15px;
            display: inline-block;
        }

        .btn-editar, .btn-eliminar {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-editar {
            background-color: orange;
            color: #fff;
        }

        .btn-eliminar {
            background-color: red;
            color: #fff;
        }

        .volver {
            margin-top: 20px;
            display: inline-block;
            background-color: gold;
            color: black;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
        }
    </style>
</head>
<body>

<h2>Listado de Gimnasios</h2>

<a class="btn" href="agregar_gimnasio.php">➕ Agregar Gimnasio</a>

<table>
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Dirección</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['nombre']) ?></td>
            <td><?= htmlspecialchars($row['direccion']) ?></td>
            <td><?= htmlspecialchars($row['telefono']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td>
                <a class="btn-editar" href="editar_gimnasio.php?id=<?= $row['id'] ?>">✏️ Editar</a>
                <a class="btn-eliminar" href="eliminar_gimnasio.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Estás seguro que deseas eliminar este gimnasio?')">🗑️ Eliminar</a>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<a class="volver" href="index.php">← Volver al menú</a>

</body>
</html>
