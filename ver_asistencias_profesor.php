<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "
    SELECT ap.id, p.nombre AS profesor, ap.fecha, ap.hora_ingreso, ap.hora_salida
    FROM asistencias_profesores ap
    JOIN profesores p ON ap.profesor_id = p.id
    WHERE ap.gimnasio_id = $gimnasio_id
    ORDER BY ap.fecha DESC, ap.hora_ingreso DESC
";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias de Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #1a1a1a;
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
<div class="contenedor">
    <h2>🕓 Asistencias de Profesores</h2>

    <table>
        <thead>
            <tr>
                <th>Profesor</th>
                <th>Fecha</th>
                <th>Hora Ingreso</th>
                <th>Hora Salida</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado->num_rows > 0): ?>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['profesor']) ?></td>
                        <td><?= htmlspecialchars($row['fecha']) ?></td>
                        <td><?= htmlspecialchars($row['hora_ingreso']) ?></td>
                        <td><?= $row['hora_salida'] ? htmlspecialchars($row['hora_salida']) : '—' ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No hay registros de asistencias.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
