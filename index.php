<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date('Y-m-d');

function obtenerPagos($conexion, $gimnasio_id, $fecha, $periodo = 'DIA') {
    $condicionFecha = $periodo === 'DIA'
        ? "fecha = '$fecha'"
        : "MONTH(fecha) = MONTH('$fecha') AND YEAR(fecha) = YEAR('$fecha')";
    $sql = "SELECT SUM(monto) as total FROM pagos WHERE gimnasio_id = $gimnasio_id AND $condicionFecha";
    $resultado = $conexion->query($sql);
    return ($fila = $resultado->fetch_assoc()) ? $fila['total'] : 0;
}

function obtenerIngresosClientes($conexion, $gimnasio_id, $fecha) {
    $sql = "SELECT c.apellido, c.nombre, c.dni, c.rfid, c.fecha_vencimiento, d.nombre AS disciplina, a.fecha_hora
            FROM asistencias a
            INNER JOIN clientes c ON a.cliente_id = c.id
            LEFT JOIN disciplinas d ON c.disciplina_id = d.id
            WHERE DATE(a.fecha_hora) = '$fecha' AND c.gimnasio_id = $gimnasio_id
            ORDER BY a.fecha_hora DESC";
    return $conexion->query($sql);
}

function obtenerIngresosProfesores($conexion, $gimnasio_id, $fecha) {
    $sql = "SELECT p.apellido, p.nombre, r.fecha_hora, r.tipo
            FROM registros_profesores r
            INNER JOIN profesores p ON r.profesor_id = p.id
            WHERE DATE(r.fecha_hora) = '$fecha' AND p.gimnasio_id = $gimnasio_id
            ORDER BY r.fecha_hora DESC";
    return $conexion->query($sql);
}

$pagosDia = obtenerPagos($conexion, $gimnasio_id, $hoy, 'DIA');
$pagosMes = obtenerPagos($conexion, $gimnasio_id, $hoy, 'MES');
$ingresosClientes = obtenerIngresosClientes($conexion, $gimnasio_id, $hoy);
$ingresosProfesores = obtenerIngresosProfesores($conexion, $gimnasio_id, $hoy);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Principal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            margin-left: 220px;
            padding: 20px;
        }
        .panel {
            background-color: #222;
            border: 1px solid #444;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        .panel h2 {
            color: #FFD700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #444;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #333;
            color: #FFD700;
        }
        tr:nth-child(even) {
            background-color: #1c1c1c;
        }
        @media (max-width: 768px) {
            .container {
                margin-left: 0;
                padding: 10px;
            }
        }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="container">
    <div class="panel">
        <h2>Resumen Económico</h2>
        <p><strong>Pagos del día:</strong> $<?= number_format($pagosDia, 2) ?></p>
        <p><strong>Pagos del mes:</strong> $<?= number_format($pagosMes, 2) ?></p>
    </div>

    <div class="panel">
        <h2>Ingresos de Clientes Hoy</h2>
        <table>
            <thead>
                <tr>
                    <th>Apellido</th>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>Disciplina</th>
                    <th>Fecha Vencimiento</th>
                    <th>Hora</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $ingresosClientes->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($fila['apellido']) ?></td>
                        <td><?= htmlspecialchars($fila['nombre']) ?></td>
                        <td><?= htmlspecialchars($fila['dni']) ?></td>
                        <td><?= htmlspecialchars($fila['disciplina']) ?></td>
                        <td><?= htmlspecialchars($fila['fecha_vencimiento']) ?></td>
                        <td><?= date('H:i', strtotime($fila['fecha_hora'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2>Ingresos de Profesores Hoy</h2>
        <table>
            <thead>
                <tr>
                    <th>Apellido</th>
                    <th>Nombre</th>
                    <th>Hora</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $ingresosProfesores->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($fila['apellido']) ?></td>
                        <td><?= htmlspecialchars($fila['nombre']) ?></td>
                        <td><?= date('H:i', strtotime($fila['fecha_hora'])) ?></td>
                        <td><?= $fila['tipo'] === 'ingreso' ? 'Entrada' : 'Salida' ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
