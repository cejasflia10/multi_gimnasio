<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'funciones.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$asistencias_clientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$cumpleanios = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel General</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin-left: 270px;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-top: 0;
        }
        .cuadro {
            background: #222;
            padding: 20px;
            margin: 10px auto;
            width: 95%;
            border-radius: 10px;
        }
        .cuadro h3 {
            margin-top: 0;
            color: gold;
        }
        table {
            width: 100%;
            color: white;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 8px;
            border-bottom: 1px solid #444;
        }
        .rojo {
            color: red;
        }
        @media (max-width: 768px) {
            body {
                margin-left: 0;
                padding: 10px;
            }
            .cuadro {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>

    <h1>Panel General - Gym System</h1>

    <div class="cuadro">
        <h3>💰 Totales</h3>
        <p>Pagos del día: $<?= $pagos_dia ?></p>
        <p>Pagos del mes: $<?= $pagos_mes ?></p>
        <p>Ventas del día: $<?= $ventas_dia ?></p>
        <p>Ventas del mes: $<?= $ventas_mes ?></p>
    </div>

    <div class="cuadro">
        <h3>👥 Ingresos de Clientes Hoy</h3>
        <table>
            <tr><th>Nombre</th><th>DNI</th><th>Disciplina</th><th>Hora</th><th>Vencimiento</th></tr>
            <?php while ($row = $asistencias_clientes->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['nombre'] . ' ' . $row['apellido'] ?></td>
                    <td><?= $row['dni'] ?></td>
                    <td><?= $row['disciplina'] ?></td>
                    <td><?= $row['hora'] ?></td>
                    <td class="<?= ($row['fecha_vencimiento'] < date('Y-m-d')) ? 'rojo' : '' ?>">
                        <?= $row['fecha_vencimiento'] ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="cuadro">
        <h3>🎂 Próximos Cumpleaños del Mes</h3>
        <ul>
            <?php while ($c = $cumpleanios->fetch_assoc()): ?>
                <li><?= $c['nombre'] . ' ' . $c['apellido'] . ' - ' . $c['fecha_nacimiento'] ?></li>
            <?php endwhile; ?>
        </ul>
    </div>

    <div class="cuadro">
        <h3>📅 Vencimientos Próximos (10 días)</h3>
        <ul>
            <?php while ($v = $vencimientos->fetch_assoc()): ?>
                <li><?= $v['nombre'] . ' ' . $v['apellido'] . ' - ' . $v['fecha_vencimiento'] ?></li>
            <?php endwhile; ?>
        </ul>
    </div>
</body>
</html>
