<?php
include 'conexion.php';
session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = date('Y-m-d');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$clientes_q = $conexion->query("SELECT c.apellido, c.nombre, a.hora
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.fecha = '$hoy' AND a.id_gimnasio = $gimnasio_id
    ORDER BY a.hora ASC");

$profesores_q = $conexion->query("SELECT p.apellido, p.nombre, ap.hora_ingreso, ap.hora_egreso
    FROM asistencias_profesores ap
    JOIN profesores p ON ap.profesor_id = p.id
    WHERE ap.fecha = '$hoy' AND ap.gimnasio_id = $gimnasio_id
    ORDER BY ap.hora_ingreso ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias del Día</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            border-bottom: 1px solid gold;
            padding-bottom: 5px;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
    </style>
</head>
<body>
    <h1>Asistencias del Día - <?= date('d/m/Y') ?></h1>

    <h2>Clientes</h2>
    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Hora Ingreso</th>
        </tr>
        <?php while ($c = $clientes_q->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($c['apellido']) ?></td>
                <td><?= htmlspecialchars($c['nombre']) ?></td>
                <td><?= substr($c['hora'], 0, 5) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Profesores</h2>
    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Ingreso</th>
            <th>Egreso</th>
        </tr>
        <?php while ($p = $profesores_q->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($p['apellido']) ?></td>
                <td><?= htmlspecialchars($p['nombre']) ?></td>
                <td><?= substr($p['hora_ingreso'], 0, 5) ?></td>
                <td><?= $p['hora_egreso'] ? substr($p['hora_egreso'], 0, 5) : '-' ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
