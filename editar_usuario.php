<?php
include 'conexion.php';
include 'menu.php';
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

$resultado = $conexion->query("SELECT usuarios.*, gimnasios.nombre AS nombre_gimnasio FROM usuarios 
                               LEFT JOIN gimnasios ON usuarios.id_gimnasio = gimnasios.id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Usuarios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin-left: 270px;
            background-color: #121212;
            color: gold;
            font-family: Arial, sans-serif;
        }
        h1 {
            text-align: center;
            color: gold;
        }
        table {
            width: 95%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: #1e1e1e;
            color: white;
        }
        th, td {
            border: 1px solid #444;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
            color: gold;
        }
        tr:hover {
            background-color: #333;
        }
        a.btn {
            padding: 5px 10px;
            margin: 2px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }
        .btn-editar {
            background-color: #3498db;
            color: white;
        }
        .btn-eliminar {
            background-color: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <h1>Usuarios del Sistema</h1>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Gimnasio</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['usuario']) ?></td>
                <td><?= $row['rol'] ?></td>
                <td><?= $row['nombre_gimnasio'] ?? 'Sin asignar' ?></td>
                <td>
                    <a class="btn btn-editar" href="editar_usuario.php?id=<?= $row['id'] ?>">Editar</a>
                    <a class="btn btn-eliminar" href="eliminar_usuario.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar este usuario?')">Eliminar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
