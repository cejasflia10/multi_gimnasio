<?php
session_start();
include 'conexion.php';

// Verifica si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// Obtener pagos del día
$pagosDia = $conexion->query("SELECT SUM(monto) AS total FROM pagos WHERE fecha = CURDATE() AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'] ?? 0;

// Obtener pagos del mes
$pagosMes = $conexion->query("SELECT SUM(monto) AS total FROM pagos WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'] ?? 0;

// Obtener ventas del día
$ventasDia = $conexion->query("SELECT SUM(total) AS total FROM ventas WHERE fecha = CURDATE() AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'] ?? 0;

// Obtener ventas del mes
$ventasMes = $conexion->query("SELECT SUM(total) AS total FROM ventas WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'] ?? 0;

// Cumpleaños próximos (dentro del mes actual)
$cumples = $conexion->query("SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE MONTH(fecha_nacimiento) = MONTH(CURDATE()) AND gimnasio_id = $gimnasio_id ORDER BY DAY(fecha_nacimiento) ASC");

// Vencimientos próximos (próximos 10 días)
$vencimientos = $conexion->query("SELECT c.nombre, c.apellido, m.fecha_vencimiento FROM membresias m INNER JOIN clientes c ON m.id_cliente = c.id WHERE m.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY) AND m.gimnasio_id = $gimnasio_id ORDER BY m.fecha_vencimiento ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - Fight Academy</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        body { background: #111; color: #f1f1f1; font-family: Arial; }
        .panel { padding: 30px; margin-left: 200px; }
        .card { background: #222; padding: 20px; margin: 10px 0; border-left: 5px solid gold; }
        h2 { color: gold; }
        .tabla { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .tabla td, .tabla th { border: 1px solid #444; padding: 8px; text-align: left; }
        .tabla th { background: #333; color: gold; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="panel">
    <h1>Bienvenido, <?php echo $_SESSION['usuario']; ?> (<?php echo $_SESSION['rol']; ?>)</h1>

    <div class="card"><h2>Pagos del Día: $<?php echo number_format($pagosDia, 2); ?></h2></div>
    <div class="card"><h2>Pagos del Mes: $<?php echo number_format($pagosMes, 2); ?></h2></div>
    <div class="card"><h2>Ventas del Día: $<?php echo number_format($ventasDia, 2); ?></h2></div>
    <div class="card"><h2>Ventas del Mes: $<?php echo number_format($ventasMes, 2); ?></h2></div>

    <div class="card">
        <h2>🎂 Próximos Cumpleaños</h2>
        <table class="tabla">
            <tr><th>Nombre</th><th>Fecha</th></tr>
            <?php while ($cumple = $cumples->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $cumple['nombre'] . ' ' . $cumple['apellido']; ?></td>
                    <td><?php echo date("d/m", strtotime($cumple['fecha_nacimiento'])); ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="card">
        <h2>⏳ Próximos Vencimientos (10 días)</h2>
        <table class="tabla">
            <tr><th>Cliente</th><th>Fecha de Vencimiento</th></tr>
            <?php while ($venc = $vencimientos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $venc['nombre'] . ' ' . $venc['apellido']; ?></td>
                    <td><?php echo date("d/m/Y", strtotime($venc['fecha_vencimiento'])); ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>
