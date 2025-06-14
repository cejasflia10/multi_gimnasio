<?php
include 'conexion.php';

// Agregar nuevo plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $duracion = $_POST['duracion'];
    $precio = $_POST['precio'];
    $max_clientes = $_POST['max_clientes'];

    $conexion->query("INSERT INTO planes_gimnasio (nombre, duracion_dias, precio, max_clientes) 
                      VALUES ('$nombre', '$duracion', '$precio', '$max_clientes')");
    header("Location: planes_gimnasio.php");
    exit;
}

// Eliminar plan
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $conexion->query("DELETE FROM planes_gimnasio WHERE id = $id");
    header("Location: planes_gimnasio.php");
    exit;
}

// Obtener planes
$planes = $conexion->query("SELECT * FROM planes_gimnasio");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes del Gimnasio</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        h1 {
            color: #ffc107;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
            margin-bottom: 20px;
        }

        th, td {
            padding: 10px;
            border: 1px solid #333;
            text-align: center;
        }

        th {
            background-color: #333;
            color: #ffc107;
        }

        form {
            display: flex;
            gap: 10px;
        }

        input[type="text"],
        input[type="number"] {
            padding: 8px;
            border: none;
            border-radius: 4px;
        }

        button {
            background-color: #ffc107;
            color: #111;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-eliminar {
            color: red;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <h1>Planes del Gimnasio</h1>

    <table>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Duración</th>
            <th>Precio</th>
            <th>Máx. Clientes</th>
            <th>Acciones</th>
        </tr>
        <?php while($row = $planes->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['nombre'] ?></td>
            <td><?= $row['duracion_dias'] ?> días</td>
            <td>$<?= $row['precio'] ?></td>
            <td><?= $row['max_clientes'] ?></td>
            <td><a class="btn-eliminar" href="?eliminar=<?= $row['id'] ?>">🗑 Eliminar</a></td>
        </tr>
        <?php endwhile; ?>
    </table>

    <h2>Agregar Nuevo Plan</h2>
    <form method="POST">
        <input type="text" name="nombre" placeholder="Nombre del plan" required>
        <input type="number" name="duracion" placeholder="Duración (días)" required>
        <input type="number" name="precio" placeholder="Precio" required>
        <input type="number" name="max_clientes" placeholder="Máx. Clientes" required>
        <button type="submit">Agregar</button>
    </form>
</body>
</html>
