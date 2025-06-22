<?php
session_start();
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

// Si es admin, ver todos. Si no, ver solo los de su gimnasio
if ($rol === 'admin') {
    $query = "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio FROM clientes 
              LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id";
} else {
    $query = "SELECT clientes.*, gimnasios.nombre AS nombre_gimnasio FROM clientes 
              LEFT JOIN gimnasios ON clientes.gimnasio_id = gimnasios.id
              WHERE gimnasio_id = $gimnasio_id";
}
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Clientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: #111;
            color: #f1f1f1;
        }
        .contenido {
            margin-left: 260px;
            padding: 20px;
        }
        h1 {
            color: #f7d774;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1a1a1a;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #333;
            text-align: left;
        }
        th {
            background-color: #222;
            color: #f7d774;
        }
        tr:nth-child(even) {
            background-color: #1f1f1f;
        }
        .action {
            color: #f7d774;
            margin-right: 10px;
            text-decoration: none;
            font-size: 1.2em;
        }
        .action:hover {
            color: #fff;
        }
        @media (max-width: 768px) {
            .contenido {
                margin-left: 0;
                padding: 10px;
            }
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th {
                position: sticky;
                top: 0;
                background-color: #222;
            }
            td {
                padding: 10px;
                border: none;
                border-bottom: 1px solid #333;
                position: relative;
                padding-left: 50%;
            }
            td:before {
                position: absolute;
                top: 10px;
                left: 10px;
                width: 45%;
                white-space: nowrap;
                font-weight: bold;
            }
        }
    </style>
</head>
<body>
<div class="contenido">
    <h1>Clientes Registrados</h1>
    <table>
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Disciplina</th>
                <th>Vencimiento</th>
                <?php if ($rol === 'admin'): ?>
                    <th>Gimnasio</th>
                <?php endif; ?>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['dni'] ?></td>
                <td><?= $row['telefono'] ?></td>
                <td><?= $row['email'] ?></td>
                <td><?= $row['disciplina'] ?></td>
                <td><?= $row['fecha_vencimiento'] ?></td>
                <?php if ($rol === 'admin'): ?>
                    <td><?= $row['nombre_gimnasio'] ?></td>
                <?php endif; ?>
                <td>
                    <a class="action" href="editar_cliente.php?id=<?= $row['id'] ?>">✏️</a>
                    <a class="action" href="eliminar_cliente.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar este cliente?')">🗑️</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
