<?php
session_start();
include 'conexion.php';

$resultado = $conexion->query("SELECT * FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gimnasios registrados</title>
    <style>
        body {
            background-color: #111;
            color: #ffc107;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            border: 1px solid #444;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #333;
        }
    </style>
</head>
<body>
    <h1>Listado de Gimnasios</h1>
    <table>
        <tr>
            <th>Nombre</th>
            <th>Dirección</th>
            <th>Teléfono</th>
            <th>Email</th>
        </tr>
        <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $row['nombre'] ?></td>
            <td><?= $row['direccion'] ?></td>
            <td><?= $row['telefono'] ?></td>
            <td><?= $row['email'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
