<?php
include 'conexion.php';
session_start();

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$resultado = $conexion->query("SELECT * FROM clientes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Clientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
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
        img.qr {
            width: 80px;
        }
    </style>
</head>
<body>
    <h1>Listado de Clientes</h1>
    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Disciplina</th>
            <th>QR</th>
        </tr>
        <?php while ($cliente = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($cliente['apellido']) ?></td>
                <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                <td><?= $cliente['dni'] ?></td>
                <td><?= htmlspecialchars($cliente['disciplina']) ?></td>
                <td><img class="qr" src="qr/qr_cliente_<?= $cliente['id'] ?>.png" alt="QR"></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
