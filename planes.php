<?php
session_start();
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

$query = "SELECT * FROM planes";
if ($rol !== 'admin') {
    $query .= " WHERE gimnasio_id = $gimnasio_id";
}
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
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
        th {
            background-color: #222;
        }
        a, button {
            color: gold;
            text-decoration: none;
        }
        .btn {
            background-color: #333;
            border: 1px solid gold;
            padding: 5px 10px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #444;
        }
    </style>
</head>
<body>
    <h1>Planes</h1>
    <table>
        <tr>
            <th>Nombre</th>
            <th>Precio</th>
            <th>Días disponibles</th>
            <th>Duración (meses)</th>
            <th>Acciones</th>
        </tr>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($fila['nombre'] ?? '') ?></td>
            <td>$<?= number_format($fila['precio'], 2, ',', '.') ?></td>
            <td><?= htmlspecialchars($fila['dias_disponibles'] ?? '') ?></td>
            <td><?= htmlspecialchars($fila['duracion'] ?? '') ?></td>
            <td><a href="eliminar_plan.php?id=<?= $fila['id'] ?>" class="btn">Eliminar</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
