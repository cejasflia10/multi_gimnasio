<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

// Obtener ID del gimnasio desde la sesión
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "
    SELECT ap.id, p.nombre AS profesor, ap.fecha, ap.hora_ingreso, ap.hora_salida
    FROM asistencias_profesor ap
    JOIN profesores p ON ap.profesor_id = p.id
    WHERE ap.id_gimnasio = $gimnasio_id
    ORDER BY ap.fecha DESC, ap.hora_ingreso DESC
";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias de Profesores</title>
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        h1 {
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Asistencias de Profesores</h1>

    <table>
        <tr>
            <th>Profesor</th>
            <th>Fecha</th>
            <th>Hora Ingreso</th>
            <th>Hora Salida</th>
        </tr>

        <?php while ($row = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['profesor']) ?></td>
                <td><?= htmlspecialchars($row['fecha']) ?></td>
                <td><?= htmlspecialchars($row['hora_ingreso']) ?></td>
                <td><?= htmlspecialchars($row['hora_salida']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
