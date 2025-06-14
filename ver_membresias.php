<?php
include 'conexion.php';

$query = "
    SELECT m.id, m.fecha_inicio, m.fecha_vencimiento, m.metodo_pago, m.monto_pagado,
           c.nombre AS nombre_cliente, c.apellido AS apellido_cliente,
           p.nombre AS nombre_plan, p.precio AS precio_plan
    FROM membresias m
    JOIN clientes c ON m.cliente_id = c.id
    JOIN planes p ON m.plan_id = p.id
    ORDER BY m.id DESC
";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Membresías</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #333; text-align: left; }
        th { background: #222; color: #ffc107; }
        tr:hover { background: #222; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Membresías registradas</h1>
    <table>
        <tr>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Método de pago</th>
            <th>Monto abonado</th>
        </tr>
        <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $row['apellido_cliente'] ?> <?= $row['nombre_cliente'] ?></td>
            <td><?= $row['nombre_plan'] ?> ($<?= $row['precio_plan'] ?>)</td>
            <td><?= $row['fecha_inicio'] ?></td>
            <td><?= $row['fecha_vencimiento'] ?></td>
            <td><?= ucfirst($row['metodo_pago']) ?></td>
            <td>$<?= number_format($row['monto_pagado'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
