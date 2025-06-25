<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'funciones.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Totales
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
    <title>Panel - Gym System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        h1, h2 {
            text-align: center;
        }

        .cuadro {
            background: #222;
            padding: 20px;
            margin: 15px auto;
            border-radius: 10px;
            max-width: 1000px;
        }

        .cuadro h3 {
            margin-top: 0;
        }

        table {
            width: 100%;
            color: white;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px;
            border-bottom: 1px solid #444;
            text-align: center;
        }

        th {
            background-color: #333;
            color: gold;
        }

        .rojo {
            color: red;
        }

        /* Menú lateral PC */
        .sidebar {
            display: none;
        }
        @media (min-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 220px;
                height: 100%;
                background-color: #000;
                padding-top: 20px;
                display: block;
                z-index: 999;
            }
            .sidebar h2 {
                color: gold;
                text-align: center;
            }
            .sidebar a {
                display: block;
                color: #ccc;
                padding: 12px 20px;
                text-decoration: none;
            }
            .sidebar a:hover {
                background-color: #333;
                color: gold;
            }
            body {
                margin-left: 220px;
            }
        }

        /* Menú inferior para celulares */
        .mobile-menu {
            display: flex;
            justify-content: space-around;
            background-color: #111;
            color: gold;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 10px 0;
            z-index: 999;
        }

        .mobile-menu a {
            color: gold;
            font-size: 20px;
        }

        @media (min-width: 768px) {
            .mobile-menu {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- Menú lateral PC -->
<div class="sidebar">
    <h2>🏋️‍♂️ Gym System</h2>
    <a href="index.php">Panel</a>
    <a href="clientes.php">Clientes</a>
    <a href="membresias.php">Membresías</a>
    <a href="asistencias.php">Asistencias</a>
    <a href="ventas.php">Ventas</a>
    <a href="profesores.php">Profesores</a>
</div>

<h1>Panel General</h1>

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

<!-- Menú inferior para celulares -->
<div class="mobile-menu">
    <a href="index.php"><i class="fas fa-home"></i></a>
    <a href="clientes.php"><i class="fas fa-users"></i></a>
    <a href="membresias.php"><i class="fas fa-id-card"></i></a>
    <a href="asistencias.php"><i class="fas fa-calendar-check"></i></a>
    <a href="ventas.php"><i class="fas fa-shopping-cart"></i></a>
</div>

</body>
</html>
