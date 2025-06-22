<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
include 'conexion.php';

function obtenerMonto($conexion, $tabla, $columnaFecha, $gimnasio_id, $tipo = 'DIA') {
    $condicion = $tipo == 'MES' ? "MONTH($columnaFecha) = MONTH(CURDATE()) AND YEAR($columnaFecha) = YEAR(CURDATE())" : "DATE($columnaFecha) = CURDATE()";
    $query = "SELECT SUM(monto) as total FROM $tabla WHERE $condicion AND gimnasio_id = $gimnasio_id";
    $resultado = $conexion->query($query);
    return ($resultado && $fila = $resultado->fetch_assoc()) ? floatval($fila['total']) : 0;
}

function obtenerVentas($conexion, $gimnasio_id, $tipo = 'DIA') {
    $condicion = $tipo == 'MES' ? "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())" : "DATE(fecha) = CURDATE()";
    $query = "SELECT SUM(monto_total) as total FROM ventas WHERE $condicion AND gimnasio_id = $gimnasio_id";
    $resultado = $conexion->query($query);
    return ($resultado && $fila = $resultado->fetch_assoc()) ? floatval($fila['total']) : 0;
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    $query = "SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE MONTH(fecha_nacimiento) = MONTH(CURDATE()) AND gimnasio_id = $gimnasio_id";
    return $conexion->query($query);
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    $query = "SELECT c.nombre, c.apellido, m.fecha_vencimiento
              FROM membresias m
              JOIN clientes c ON m.cliente_id = c.id
              WHERE m.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
              AND c.gimnasio_id = $gimnasio_id
              ORDER BY m.fecha_vencimiento ASC";
    return $conexion->query($query);
}

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $query = "SELECT c.apellido, c.nombre, c.dni, d.nombre AS disciplina, m.fecha_vencimiento
              FROM asistencias a
              JOIN clientes c ON a.cliente_id = c.id
              LEFT JOIN disciplinas d ON c.disciplina_id = d.id
              LEFT JOIN membresias m ON m.cliente_id = c.id
              WHERE DATE(a.fecha_hora) = CURDATE() AND c.gimnasio_id = $gimnasio_id";
    return $conexion->query($query);
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    $query = "SELECT p.apellido, p.nombre, r.fecha
              FROM registros_profesores r
              JOIN profesores p ON r.profesor_id = p.id
              WHERE DATE(r.fecha) = CURDATE() AND p.gimnasio_id = $gimnasio_id";
    return $conexion->query($query);
}

// Datos
$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerVentas($conexion, $gimnasio_id, 'DIA');
$ventas_mes = obtenerVentas($conexion, $gimnasio_id, 'MES');
$cumpleanios = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
$ingresos_clientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$ingresos_profesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - Fight Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .panel {
            margin-left: 230px;
            padding: 20px;
        }
        .section {
            margin-top: 20px;
            background: #222;
            padding: 15px;
            border-radius: 10px;
        }
        table {
            width: 100%;
            background-color: #333;
            color: #f1f1f1;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            border-bottom: 1px solid #444;
            text-align: left;
        }
        th {
            color: gold;
        }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="panel">
    <h1>Resumen Económico</h1>
    <p><strong>Pagos del día:</strong> $<?= number_format($pagos_dia, 2) ?></p>
    <p><strong>Pagos del mes:</strong> $<?= number_format($pagos_mes, 2) ?></p>
    <p><strong>Ventas del día:</strong> $<?= number_format($ventas_dia, 2) ?></p>
    <p><strong>Ventas del mes:</strong> $<?= number_format($ventas_mes, 2) ?></p>

    <div class="section">
        <h2>Ingresos de Clientes Hoy</h2>
        <table>
            <tr><th>Apellido</th><th>Nombre</th><th>DNI</th><th>Disciplina</th><th>Fecha Vencimiento</th></tr>
            <?php while($row = $ingresos_clientes->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['apellido'] ?></td>
                    <td><?= $row['nombre'] ?></td>
                    <td><?= $row['dni'] ?></td>
                    <td><?= $row['disciplina'] ?></td>
                    <td><?= $row['fecha_vencimiento'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="section">
        <h2>Ingresos de Profesores Hoy</h2>
        <table>
            <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th></tr>
            <?php while($row = $ingresos_profesores->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['apellido'] ?></td>
                    <td><?= $row['nombre'] ?></td>
                    <td><?= $row['fecha'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="section">
        <h2>Próximos Cumpleaños</h2>
        <table>
            <tr><th>Nombre</th><th>Apellido</th><th>Fecha de Nacimiento</th></tr>
            <?php while($row = $cumpleanios->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['nombre'] ?></td>
                    <td><?= $row['apellido'] ?></td>
                    <td><?= $row['fecha_nacimiento'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="section">
        <h2>Próximos Vencimientos (10 días)</h2>
        <table>
            <tr><th>Nombre</th><th>Apellido</th><th>Fecha Vencimiento</th></tr>
            <?php while($row = $vencimientos->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['nombre'] ?></td>
                    <td><?= $row['apellido'] ?></td>
                    <td><?= $row['fecha_vencimiento'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

</body>
</html>
