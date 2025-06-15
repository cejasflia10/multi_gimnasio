<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) echo '<p style="color: gold; background: #222; padding: 10px;">Bienvenido <strong>' . $_SESSION['usuario'] . '</strong> | Rol: <strong>' . $_SESSION['rol'] . '</strong></p>';

$id_gimnasio = $_SESSION['id_gimnasio'];
$hoy = date('Y-m-d');
$mes_actual = date('Y-m');

// Ingresos del día
$ingresos_dia = $conexion->query("SELECT SUM(monto_pagado) as total FROM membresias WHERE fecha_inicio = '$hoy' AND id_gimnasio = $id_gimnasio")->fetch_assoc()['total'] ?? 0;
$ventas_dia = $conexion->query("SELECT SUM(precio_venta) as total FROM ventas WHERE fecha = '$hoy' AND id_gimnasio = $id_gimnasio")->fetch_assoc()['total'] ?? 0;

// Ingresos del mes
$ingresos_mes = $conexion->query("SELECT SUM(monto_pagado) as total FROM membresias WHERE DATE_FORMAT(fecha_inicio, '%Y-%m') = '$mes_actual' AND id_gimnasio = $id_gimnasio")->fetch_assoc()['total'] ?? 0;
$ventas_mes = $conexion->query("SELECT SUM(precio_venta) as total FROM ventas WHERE DATE_FORMAT(fecha, '%Y-%m') = '$mes_actual' AND id_gimnasio = $id_gimnasio")->fetch_assoc()['total'] ?? 0;

// Cumpleaños del mes
$cumples = $conexion->query("SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE MONTH(fecha_nacimiento) = MONTH(NOW()) AND id_gimnasio = $id_gimnasio");

// Próximos vencimientos (5 días)
$vencimientos = $conexion->query("SELECT c.nombre, c.apellido, m.fecha_vencimiento FROM membresias m JOIN clientes c ON m.cliente_id = c.id WHERE m.id_gimnasio = $id_gimnasio AND m.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 5 DAY)");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <style>
        body { background-color: #111; color: #fff; font-family: Arial; padding: 30px; }
        .panel { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .card {
            background: #222; border: 1px solid #333; padding: 20px; border-radius: 10px;
        }
        .card h3 { color: #ffc107; margin-top: 0; }
        .card p { font-size: 1.2em; }
        table { width: 100%; border-collapse: collapse; background-color: #222; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #444; }
        th { background-color: #333; color: #ffc107; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
    <h1>📊 Panel de Control</h1>
    <div class="panel">
        <div class="card">
            <h3>Ingresos del Día</h3>
            <p>$<?php echo number_format($ingresos_dia + $ventas_dia, 2); ?></p>
        </div>
        <div class="card">
            <h3>Ingresos del Mes</h3>
            <p>$<?php echo number_format($ingresos_mes + $ventas_mes, 2); ?></p>
        </div>
        <div class="card">
            <h3>Ventas del Mes</h3>
            <p>$<?php echo number_format($ventas_mes, 2); ?></p>
        </div>
    </div>

    <div class="card" style="margin-top:30px;">
        <h3>🎉 Cumpleaños del Mes</h3>
        <table>
            <tr><th>Nombre</th><th>Apellido</th><th>Fecha</th></tr>
            <?php while($c = $cumples->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                <td><?php echo htmlspecialchars($c['apellido']); ?></td>
                <td><?php echo date('d/m', strtotime($c['fecha_nacimiento'])); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <div class="card" style="margin-top:30px;">
        <h3>📅 Próximos Vencimientos</h3>
        <table>
            <tr><th>Nombre</th><th>Apellido</th><th>Vence el</th></tr>
            <?php while($v = $vencimientos->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($v['nombre']); ?></td>
                <td><?php echo htmlspecialchars($v['apellido']); ?></td>
                <td><?php echo date('d/m/Y', strtotime($v['fecha_vencimiento'])); ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</body>
</html>
