<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) {
    die('Acceso no autorizado');
}
$id_gimnasio = $_SESSION['id_gimnasio'];

$sql = "SELECT m.*, c.nombre AS cliente_nombre, c.apellido, p.nombre AS plan_nombre 
        FROM membresias m
        JOIN clientes c ON m.cliente_id = c.id
        JOIN planes p ON m.plan_id = p.id
        WHERE m.id_gimnasio = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_gimnasio);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Membresías</title>
    <style>
        body {
            background-color: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            color: #ffc107;
            text-align: center;
        }
        table {
            width: 100%;
            background-color: #222;
            color: #fff;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #555;
            text-align: center;
        }
        th {
            background-color: #333;
            color: #ffc107;
        }
        tr:hover {
            background-color: #444;
        }
    </style>
</head>
<body>
    <h1>Membresías Actuales</h1>
    <table>
        <tr>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Fecha Inicio</th>
            <th>Vencimiento</th>
            <th>Clases Restantes</th>
            <th>Monto</th>
            <th>Método de Pago</th>
        </tr>
        <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['apellido'] . ', ' . $row['cliente_nombre']; ?></td>
            <td><?php echo $row['plan_nombre']; ?></td>
            <td><?php echo $row['fecha_inicio']; ?></td>
            <td><?php echo $row['fecha_vencimiento']; ?></td>
            <td><?php echo $row['clases_restantes']; ?></td>
            <td>$<?php echo $row['monto_pagado']; ?></td>
            <td><?php echo $row['metodo_pago']; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
