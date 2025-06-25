<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// FUNCIONES
function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES'
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";
    $columna = ($tabla === 'ventas') ? 'monto_total' : (($tabla === 'membresias') ? 'total' : 'monto');
    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND gimnasio_id = $gimnasio_id";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    $mes_actual = date('m');
    $sql = "SELECT nombre, apellido, fecha_nacimiento FROM clientes 
            WHERE MONTH(fecha_nacimiento) = $mes_actual AND gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $diez_dias_despues = date('Y-m-d', strtotime('+10 days'));
    $sql = "SELECT clientes.nombre, clientes.apellido, membresias.fecha_vencimiento 
            FROM membresias 
            JOIN clientes ON clientes.id = membresias.cliente_id 
            WHERE membresias.fecha_vencimiento BETWEEN '$hoy' AND '$diez_dias_despues'
            AND membresias.gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

// DATOS
$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$cumples = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Principal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_menu.css"> <!-- tu estilo si usás uno externo -->
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h2 {
            text-align: center;
        }
        .menu-toggle {
            display: none;
            background: #222;
            color: gold;
            border: none;
            padding: 10px;
            font-size: 20px;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1000;
        }
        .panel {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background-color: #222;
            border-radius: 12px;
            padding: 20px;
            width: 200px;
            text-align: center;
            box-shadow: 0 0 8px rgba(0,0,0,0.7);
        }
        .section {
            background-color: #222;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
        }
        table {
            width: 100%;
            color: gold;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #444;
        }

        @media (max-width: 768px) {
            .panel { flex-direction: column; align-items: center; }
            .card { width: 100%; max-width: 300px; }
            .menu-toggle { display: block; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>

<!-- Botón menú para celulares -->
<button class="menu-toggle" onclick="toggleMenu()">☰</button>

<script>
function toggleMenu() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.style.display = (sidebar.style.display === 'block') ? 'none' : 'block';
}
</script>

<h2>Panel Principal del Gimnasio</h2>

<div class="panel">
    <div class="card">
        <h3>Pagos Hoy</h3>
        <p>$<?= number_format($pagos_dia, 0, ',', '.') ?></p>
    </div>
    <div class="card">
        <h3>Pagos Mes</h3>
        <p>$<?= number_format($pagos_mes, 0, ',', '.') ?></p>
    </div>
    <div class="card">
        <h3>Ventas Hoy</h3>
        <p>$<?= number_format($ventas_dia, 0, ',', '.') ?></p>
    </div>
    <div class="card">
        <h3>Ventas Mes</h3>
        <p>$<?= number_format($ventas_mes, 0, ',', '.') ?></p>
    </div>
</div>

<div class="section">
    <h3>Próximos Cumpleaños</h3>
    <table>
        <tr><th>Nombre</th><th>Fecha</th></tr>
        <?php while ($c = $cumples->fetch_assoc()): ?>
            <tr>
                <td><?= $c['nombre'] . ' ' . $c['apellido'] ?></td>
                <td><?= date('d/m', strtotime($c['fecha_nacimiento'])) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

<div class="section">
    <h3>Próximos Vencimientos</h3>
    <table>
        <tr><th>Cliente</th><th>Vencimiento</th></tr>
        <?php while ($v = $vencimientos->fetch_assoc()): ?>
            <tr>
                <td><?= $v['nombre'] . ' ' . $v['apellido'] ?></td>
                <td><?= date('d/m/Y', strtotime($v['fecha_vencimiento'])) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
