<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu.php';

$fecha_actual = date('Y-m-d');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener nombre del gimnasio
$resultado_nombre = $conexion->query("SELECT nombre FROM gimnasios WHERE id = $gimnasio_id");
$gimnasio_nombre = ($resultado_nombre && $resultado_nombre->num_rows > 0) ? $resultado_nombre->fetch_assoc()['nombre'] : "Gimnasio";

// Obtener asistencias de clientes
$asistencias_clientes = $conexion->query("
    SELECT c.apellido, c.nombre, a.fecha, a.hora
    FROM asistencias_clientes AS a
    INNER JOIN clientes AS c ON a.cliente_id = c.id
    WHERE c.gimnasio_id = $gimnasio_id AND a.fecha = '$fecha_actual'
    ORDER BY a.hora DESC
");

// Obtener asistencias de profesores
$asistencias_profesores = $conexion->query("
    SELECT p.apellido, p.nombre, a.hora_ingreso, a.hora_salida
    FROM asistencias_profesores AS a
    INNER JOIN profesores AS p ON a.profesor_id = p.id
    WHERE p.gimnasio_id = $gimnasio_id AND a.fecha = '$fecha_actual'
    ORDER BY a.hora_ingreso DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control - <?= $gimnasio_nombre ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h1, h2 {
            color: #FFD700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        th, td {
            border: 1px solid #FFD700;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
    </style>
</head>
<body>
    <h1><?= strtoupper($gimnasio_nombre) ?></h1>
    <h2>Bienvenido al Panel de Control</h2>

    <h3>Asistencias de Clientes - <?= $fecha_actual ?></h3>
    <table>
        <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>
        <?php while($row = $asistencias_clientes->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= $row['hora'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h3>Asistencias de Profesores - <?= $fecha_actual ?></h3>
    <table>
        <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Salida</th></tr>
        <?php while($row = $asistencias_profesores->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['hora_ingreso'] ?? '—' ?></td>
                <td><?= $row['hora_salida'] ?? '—' ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
