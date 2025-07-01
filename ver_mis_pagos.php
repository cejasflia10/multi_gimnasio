
<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");
include 'menu_cliente.php';

// Obtener pagos desde membresías
$pagos = $conexion->query("
    SELECT fecha_inicio, fecha_vencimiento, total, metodo_pago
    FROM membresias
    WHERE cliente_id = $cliente_id
    ORDER BY fecha_inicio DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>💳 Mis Pagos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 { text-align: center; }
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
    </style>
</head>
<body>
    <h1>💳 Mis Pagos</h1>
    <?php if ($pagos->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha Inicio</th>
                    <th>Fecha Vencimiento</th>
                    <th>Total</th>
                    <th>Método de Pago</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $pagos->fetch_assoc()): ?>
                    <tr>
                        <td><?= $fila['fecha_inicio'] ?></td>
                        <td><?= $fila['fecha_vencimiento'] ?></td>
                        <td>$<?= number_format($fila['total'], 2, ',', '.') ?></td>
                        <td><?= $fila['metodo_pago'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center;">No hay pagos registrados.</p>
    <?php endif; ?>
</body>
</html>
