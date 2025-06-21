<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$fecha_hoy = date('Y-m-d');

// CONSULTA CLIENTES CON ASISTENCIAS DEL DÍA
$clientes_query = "
    SELECT c.apellido, c.nombre, m.clases_restantes, a.hora
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    LEFT JOIN membresias m ON m.cliente_id = c.id
    WHERE a.fecha = '$fecha_hoy' AND a.id_gimnasio = $gimnasio_id
    ORDER BY a.hora DESC
";

$result_clientes = $conexion->query($clientes_query);

// CONSULTA PROFESORES CON INGRESO Y EGRESO DEL DÍA
$profesores_query = "
    SELECT p.apellido, p.nombre, r.ingreso, r.egreso
    FROM rfid_profesores_registros r
    JOIN profesores p ON r.profesor_id = p.id
    WHERE r.fecha = '$fecha_hoy' AND r.gimnasio_id = $gimnasio_id
    ORDER BY r.ingreso DESC
";

$result_profesores = $conexion->query($profesores_query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias del Día</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h2 {
            color: gold;
            margin-top: 40px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
            margin-bottom: 40px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #444;
            text-align: center;
        }
        th {
            background-color: #333;
            color: gold;
        }
        td {
            color: #eee;
        }
    </style>
</head>
<body>

<h2>🕒 Ingresos de Clientes Hoy</h2>
<table>
    <tr>
        <th>Apellido</th>
        <th>Nombre</th>
        <th>Clases Restantes</th>
        <th>Hora</th>
    </tr>
    <?php while($row = $result_clientes->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['apellido']) ?></td>
        <td><?= htmlspecialchars($row['nombre']) ?></td>
        <td><?= htmlspecialchars($row['clases_restantes'] ?? '0') ?></td>
        <td><?= htmlspecialchars($row['hora']) ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<h2>👨‍🏫 Ingresos y Egresos de Profesores Hoy</h2>
<table>
    <tr>
        <th>Profesor</th>
        <th>Ingreso</th>
        <th>Egreso</th>
    </tr>
    <?php while($row = $result_profesores->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['apellido']) . ' ' . htmlspecialchars($row['nombre']) ?></td>
        <td><?= htmlspecialchars($row['ingreso']) ?></td>
        <td><?= htmlspecialchars($row['egreso']) ?></td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
