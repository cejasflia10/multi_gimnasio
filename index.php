<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

include 'menu_moderno.php';
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;

// Pagos y ventas
$hoy = date('Y-m-d');
$mes = date('Y-m');

// Totales del día
$query_pago_dia = $conexion->query("SELECT SUM(monto) AS total FROM pagos WHERE fecha = '$hoy' AND gimnasio_id = '$gimnasio_id'");
$pago_dia = $query_pago_dia->fetch_assoc()['total'] ?? 0;

$query_venta_dia = $conexion->query("SELECT SUM(total) AS total FROM ventas WHERE fecha = '$hoy' AND gimnasio_id = '$gimnasio_id'");
$venta_dia = $query_venta_dia->fetch_assoc()['total'] ?? 0;

// Totales del mes
$query_pago_mes = $conexion->query("SELECT SUM(monto) AS total FROM pagos WHERE fecha LIKE '$mes%' AND gimnasio_id = '$gimnasio_id'");
$pago_mes = $query_pago_mes->fetch_assoc()['total'] ?? 0;

$query_venta_mes = $conexion->query("SELECT SUM(total) AS total FROM ventas WHERE fecha LIKE '$mes%' AND gimnasio_id = '$gimnasio_id'");
$venta_mes = $query_venta_mes->fetch_assoc()['total'] ?? 0;

// Cumpleaños
$mes_actual = date('m');
$proximos_cumples = $conexion->query("SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE MONTH(fecha_nacimiento) = '$mes_actual' AND gimnasio_id = '$gimnasio_id' ORDER BY DAY(fecha_nacimiento) ASC");

// Vencimientos
$fecha_vencimiento = date('Y-m-d', strtotime('+10 days'));
$vencimientos = $conexion->query("SELECT c.nombre, c.apellido, m.fecha_vencimiento FROM membresias m JOIN clientes c ON m.cliente_id = c.id WHERE m.fecha_vencimiento <= '$fecha_vencimiento' AND c.gimnasio_id = '$gimnasio_id'");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control - Fight Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
        }

        .contenido {
            margin-left: 250px;
            padding: 20px;
        }

        h1 {
            color: gold;
        }

        .tarjeta {
            background-color: #222;
            color: gold;
            border-left: 6px solid gold;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .tarjeta h2 {
            margin: 0;
            font-size: 20px;
        }

        .tarjeta p {
            font-size: 24px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .contenido {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<div class="contenido">
    <h1>Bienvenido al Panel</h1>

    <div class="tarjeta">
        <h2>Pagos del día</h2>
        <p>$<?= number_format($pago_dia, 2, ',', '.') ?></p>
    </div>
    <div class="tarjeta">
        <h2>Pagos del mes</h2>
        <p>$<?= number_format($pago_mes, 2, ',', '.') ?></p>
    </div>
    <div class="tarjeta">
        <h2>Ventas del día</h2>
        <p>$<?= number_format($venta_dia, 2, ',', '.') ?></p>
    </div>
    <div class="tarjeta">
        <h2>Ventas del mes</h2>
        <p>$<?= number_format($venta_mes, 2, ',', '.') ?></p>
    </div>

    <div class="tarjeta">
        <h2>Próximos cumpleaños del mes</h2>
        <ul>
            <?php while ($cumple = $proximos_cumples->fetch_assoc()): ?>
                <li><?= $cumple['nombre'] . ' ' . $cumple['apellido'] . ' - ' . date('d/m', strtotime($cumple['fecha_nacimiento'])) ?></li>
            <?php endwhile; ?>
        </ul>
    </div>

    <div class="tarjeta">
        <h2>Vencimientos próximos (10 días)</h2>
        <ul>
            <?php while ($vto = $vencimientos->fetch_assoc()): ?>
                <li><?= $vto['nombre'] . ' ' . $vto['apellido'] . ' - ' . date('d/m/Y', strtotime($vto['fecha_vencimiento'])) ?></li>
            <?php endwhile; ?>
        </ul>
    </div>
</div>

</body>
</html>
