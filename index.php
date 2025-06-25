
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
    <title>Panel General - <?= $nombre_gimnasio ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background:#111; color:gold; font-family:Arial; margin:0; padding:20px 40px 20px 260px; }
        h1, h2, h3 { text-align:center; }
        .cuadro {
            background:#222;
            margin:15px 0;
            padding:20px;
            border-radius:8px;
            width:100%;
        }
        table {
            width:100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding:10px;
            text-align:left;
            border-bottom:1px solid #444;
            color:white;
        }
        .rojo { color:red; }
        ul { padding-left: 20px; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <h1>Panel General - <?= $nombre_gimnasio ?></h1>

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
