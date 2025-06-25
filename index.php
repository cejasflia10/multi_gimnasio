<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

// Funciones para obtener montos
function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES'
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";
    $columna = ($tabla === 'ventas') ? 'monto_total' : (($tabla === 'membresias') ? 'total' : 'monto');
    $sql = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND gimnasio_id = $gimnasio_id";
    $resultado = $conexion->query($sql);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

// Función para cumpleaños
function obtenerCumpleanios($conexion, $gimnasio_id) {
    $hoy_mes = date('m');
    $sql = "SELECT nombre, apellido, fecha_nacimiento FROM clientes 
            WHERE MONTH(fecha_nacimiento) = $hoy_mes AND gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

// Función para vencimientos próximos
function obtenerVencimientos($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $limite = date('Y-m-d', strtotime('+10 days'));
    $sql = "SELECT clientes.nombre, clientes.apellido, membresias.fecha_vencimiento 
            FROM membresias 
            JOIN clientes ON clientes.id = membresias.cliente_id 
            WHERE membresias.fecha_vencimiento BETWEEN '$hoy' AND '$limite'
            AND membresias.gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$cumpleanios = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel General</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        h2 { text-align: center; }
        .tarjetas { display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; margin-bottom: 30px; }
        .tarjeta {
            background-color: #222; border-radius: 12px; padding: 20px; width: 240px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5); text-align: center;
        }
        .lista { background-color: #222; padding: 20px; margin-bottom: 20px; border-radius: 10px; }
        table { width: 100%; color: gold; border-collapse: collapse; }
        th, td { padding: 8px; border-bottom: 1px solid #444; text-align: left; }
    </style>
</head>
<body>
    <h2>Panel General del Gimnasio</h2>
    <div class="tarjetas">
        <div class="tarjeta"><h3>Pagos Hoy</h3><p>$<?= number_format($pagos_dia, 0, ',', '.') ?></p></div>
        <div class="tarjeta"><h3>Pagos del Mes</h3><p>$<?= number_format($pagos_mes, 0, ',', '.') ?></p></div>
        <div class="tarjeta"><h3>Ventas Hoy</h3><p>$<?= number_format($ventas_dia, 0, ',', '.') ?></p></div>
        <div class="tarjeta"><h3>Ventas del Mes</h3><p>$<?= number_format($ventas_mes, 0, ',', '.') ?></p></div>
    </div>

    <div class="lista">
        <h3>Próximos Cumpleaños</h3>
        <table>
            <tr><th>Nombre</th><th>Fecha</th></tr>
            <?php while ($c = $cumpleanios->fetch_assoc()): ?>
                <tr><td><?= $c['nombre'] . ' ' . $c['apellido'] ?></td><td><?= date('d/m', strtotime($c['fecha_nacimiento'])) ?></td></tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="lista">
        <h3>Próximos Vencimientos</h3>
        <table>
            <tr><th>Cliente</th><th>Vencimiento</th></tr>
            <?php while ($v = $vencimientos->fetch_assoc()): ?>
                <tr><td><?= $v['nombre'] . ' ' . $v['apellido'] ?></td><td><?= date('d/m/Y', strtotime($v['fecha_vencimiento'])) ?></td></tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
