<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
include 'menu.php';
include 'conexion.php';

// Obtener gimnasio_id del usuario logueado
$gimnasio_id = $_SESSION['gimnasio_id'];

// Pagos del día
$pagosDia = $conexion->query("SELECT SUM(monto) AS total FROM pagos WHERE DATE(fecha_pago) = CURDATE() AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'] ?? 0;

// Pagos del mes
$pagosMes = $conexion->query("SELECT SUM(monto) AS total FROM pagos WHERE MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE()) AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'] ?? 0;

// Ventas del día
$ventasDia = $conexion->query("SELECT SUM(monto_total) AS total FROM ventas WHERE DATE(fecha) = CURDATE() AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'] ?? 0;

// Ventas del mes
$ventasMes = $conexion->query("SELECT SUM(monto_total) AS total FROM ventas WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'] ?? 0;

// Próximos cumpleaños
$proximosCumples = $conexion->query("SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE gimnasio_id = $gimnasio_id AND DATE_FORMAT(fecha_nacimiento, '%m-%d') BETWEEN DATE_FORMAT(CURDATE(), '%m-%d') AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '%m-%d') ORDER BY fecha_nacimiento");

// Vencimientos
$vencimientos = $conexion->query("SELECT c.nombre, c.apellido, m.fecha_vencimiento FROM membresias m JOIN clientes c ON m.cliente_id = c.id WHERE m.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY) AND m.gimnasio_id = $gimnasio_id ORDER BY m.fecha_vencimiento ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - Fight Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }
        .contenido {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            background-color: #222;
            border-left: 5px solid #f1c40f;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        h2 {
            color: #f1c40f;
        }
        @media (max-width: 768px) {
            .contenido {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
<div class="contenido">
    <h2>Bienvenido al Panel</h2>

    <div class="card">💰 <strong>Pagos del día:</strong> $<?= number_format($pagosDia, 0, ',', '.') ?></div>
    <div class="card">📅 <strong>Pagos del mes:</strong> $<?= number_format($pagosMes, 0, ',', '.') ?></div>
    <div class="card">🛍️ <strong>Ventas del día:</strong> $<?= number_format($ventasDia, 0, ',', '.') ?></div>
    <div class="card">📈 <strong>Ventas del mes:</strong> $<?= number_format($ventasMes, 0, ',', '.') ?></div>

    <h2>🎂 Próximos Cumpleaños</h2>
    <div class="card">
        <?php while ($cumple = $proximosCumples->fetch_assoc()): ?>
            <?= $cumple['nombre'] . ' ' . $cumple['apellido'] . ' - ' . date('d/m', strtotime($cumple['fecha_nacimiento'])) ?><br>
        <?php endwhile; ?>
    </div>

    <h2>⏳ Próximos Vencimientos</h2>
    <div class="card">
        <?php while ($venc = $vencimientos->fetch_assoc()): ?>
            <?= $venc['nombre'] . ' ' . $venc['apellido'] . ' - Vence: ' . date('d/m/Y', strtotime($venc['fecha_vencimiento'])) ?><br>
        <?php endwhile; ?>
    </div>
</div>
</body>
</html>
