
<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) {
    die("Acceso denegado.");
}

// Obtener reservas del cliente
$reservas = $conexion->query("
    SELECT r.fecha, d.nombre AS dia, h.hora_inicio, h.hora_fin, p.apellido AS profesor
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN dias d ON t.dia_id = d.id
    JOIN horarios h ON t.horario_id = h.id
    JOIN profesores p ON t.profesor_id = p.id
    WHERE r.cliente_id = $cliente_id
    ORDER BY r.fecha DESC, h.hora_inicio
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Turnos Reservados</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        th, td {
            padding: 10px;
            border: 1px solid gold;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        @media (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <h1>Mis Turnos Reservados</h1>

    <?php if ($reservas->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Horario</th>
                    <th>Profesor</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = $reservas->fetch_assoc()): ?>
                    <tr>
                        <td><?= $r['fecha'] ?></td>
                        <td><?= $r['dia'] ?></td>
                        <td><?= $r['hora_inicio'] ?> - <?= $r['hora_fin'] ?></td>
                        <td><?= $r['profesor'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align:center;">No tenés turnos reservados todavía.</p>
    <?php endif; ?>
</body>
</html>
