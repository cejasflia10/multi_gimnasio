<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES'
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";

    $columna = ($tabla === 'ventas') ? 'monto_total' : (($tabla === 'membresias') ? 'total' : 'monto');

    $sql = "SELECT SUM($columna) AS total FROM $tabla 
            WHERE $condicion AND gimnasio_id = $gimnasio_id";
    $resultado = $conexion->query($sql);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    $mes = date('m');
    $sql = "SELECT nombre, apellido, fecha_nacimiento FROM clientes 
            WHERE MONTH(fecha_nacimiento) = $mes AND gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $fin = date('Y-m-d', strtotime('+10 days'));
    $sql = "SELECT clientes.nombre, clientes.apellido, membresias.fecha_vencimiento 
            FROM membresias 
            JOIN clientes ON clientes.id = membresias.cliente_id 
            WHERE membresias.fecha_vencimiento BETWEEN '$hoy' AND '$fin'
              AND membresias.gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

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
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
        }
        .contenido {
            margin-left: 270px;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 30px;
        }
        .panel {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 40px;
        }
        .card {
            background-color: #222;
            padding: 20px;
            border-radius: 12px;
            width: 220px;
            text-align: center;
            box-shadow: 0 0 10px rgba(0,0,0,0.6);
        }
        .section {
            background-color: #222;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            color: gold;
        }
        th, td {
            padding: 8px;
            border-bottom: 1px solid #444;
            text-align: left;
        }
        @media (max-width: 768px) {
            .contenido {
                margin-left: 0;
                padding: 10px;
            }
            .panel {
                flex-direction: column;
                align-items: center;
            }
            .card {
                width: 90%;
            }
        }
    </style>
</head>
<body>
<div class="contenido">
    <h2>Panel Principal del Gimnasio</h2>

    <div class="panel">
        <div class="card"><h3>Pagos Hoy</h3><p>$<?= number_format($pagos_dia, 0, ',', '.') ?></p></div>
        <div class="card"><h3>Pagos Mes</h3><p>$<?= number_format($pagos_mes, 0, ',', '.') ?></p></div>
        <div class="card"><h3>Ventas Hoy</h3><p>$<?= number_format($ventas_dia, 0, ',', '.') ?></p></div>
        <div class="card"><h3>Ventas Mes</h3><p>$<?= number_format($ventas_mes, 0, ',', '.') ?></p></div>
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
</div>
</body>
</html>
