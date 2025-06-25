<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'funciones.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';
$nombre_gimnasio = '';

if ($gimnasio_id) {
    $res = $conexion->query("SELECT nombre FROM gimnasios WHERE id = $gimnasio_id");
    if ($fila = $res->fetch_assoc()) {
        $nombre_gimnasio = $fila['nombre'];
    }
}

$pagos_dia = obtenerMonto($conexion, 'membresias', 'fecha_pago', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'membresias', 'fecha_pago', $gimnasio_id, 'MES');
$ventas_mes = obtenerVentasTotales($conexion, $gimnasio_id);
$asistencias_clientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$cumpleanios = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel General - <?= $nombre_gimnasio ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px 40px 20px 260px;
        }
        h1, h2 {
            text-align: center;
        }
        .cuadro {
            background: #222;
            margin: 15px 0;
            padding: 20px;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #444;
        }
        .rojo {
            color: red;
        }
        ul {
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <h1>Panel General - <?= $nombre_gimnasio ?></h1>

    <div class="cuadro">
        <h2>Resumen Económico</h2>
        <p><strong>Pagos del Día:</strong> $<?= $pagos_dia ?></p>
        <p><strong>Pagos del Mes:</strong> $<?= $pagos_mes ?></p>
        <p><strong>Ventas del Mes (Total):</strong> $<?= $ventas_mes ?></p>
    </div>

    <div class="cuadro">
        <h2>Asistencias del Día</h2>
        <?= $asistencias_clientes ?>
    </div>

    <div class="cuadro">
        <h2>Próximos Cumpleaños</h2>
        <?= $cumpleanios ?>
    </div>

    <div class="cuadro">
        <h2>Vencimientos Próximos</h2>
        <?= $vencimientos ?>
    </div>
</body>
</html>
