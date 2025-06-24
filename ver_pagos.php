<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "
SELECT p.*, 
       c.apellido, c.nombre, c.dni, 
       pl.nombre AS nombre_plan
FROM pagos p
JOIN clientes c ON p.cliente_id = c.id
JOIN membresias m ON p.membresia_id = m.id
JOIN planes pl ON m.plan_id = pl.id
WHERE p.gimnasio_id = $gimnasio_id AND MONTH(p.fecha) = MONTH(CURDATE()) AND YEAR(p.fecha) = YEAR(CURDATE())
ORDER BY p.fecha DESC
";

$resultado = $conexion->query($query);

// Total mensual
$total_query = $conexion->query("
SELECT SUM(monto) AS total_mes 
FROM pagos 
WHERE gimnasio_id = $gimnasio_id AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
");
$total = $total_query->fetch_assoc()['total_mes'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos Mensuales</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 15px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #444;
            text-align: left;
        }
        th {
            background-color: #222;
        }
        .boton {
            background-color: gold;
            color: black;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
        }
        .total {
            font-size: 18px;
            margin-top: 20px;
            text-align: right;
        }

        @media screen and (max-width: 700px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead {
                display: none;
            }
            tr {
                border: 1px solid #333;
                margin-bottom: 15px;
                padding: 10px;
                border-radius: 6px;
            }
            td {
                border: none;
                padding: 8px 10px;
            }
            td::before {
                content: attr(data-label);
                font-weight: bold;
                display: block;
                color: #ccc;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>

<h1>Pagos del Mes</h1>

<table>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>DNI</th>
            <th>Fecha</th>
            <th>Forma de Pago</th>
            <th>Monto</th>
            <th>Plan</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td data-label="Cliente"><?= $row['apellido'] . ', ' . $row['nombre'] ?></td>
            <td data-label="DNI"><?= $row['dni'] ?></td>
            <td data-label="Fecha"><?= $row['fecha'] ?></td>
            <td data-label="Forma"><?= ucfirst($row['forma_pago']) ?></td>
            <td data-label="Monto">$<?= number_format($row['monto'], 2) ?></td>
            <td data-label="Plan"><?= $row['nombre_plan'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<div class="total">Total del mes: <strong>$<?= number_format($total, 2) ?></strong></div>

<div style="text-align: center; margin-top: 30px;">
    <a href="agregar_pago.php" class="boton">Agregar Pago</a>
    <a href="index.php" class="boton">Volver al menú</a>
</div>

</body>
</html>
