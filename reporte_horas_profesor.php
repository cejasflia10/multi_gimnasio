<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    die('Acceso no autorizado.');
}

$mes = $_GET['mes'] ?? date('Y-m');

$sql = "SELECT p.nombre, p.apellido, 
               DATE(a.hora_entrada) as dia, 
               SEC_TO_TIME(SUM(TIMESTAMPDIFF(SECOND, a.hora_entrada, a.hora_salida))) as horas_totales
        FROM asistencias_profesor a
        JOIN profesores p ON a.profesor_id = p.id
        WHERE a.gimnasio_id = ? AND a.hora_salida IS NOT NULL AND DATE_FORMAT(a.hora_entrada, '%Y-%m') = ?
        GROUP BY p.id, dia
        ORDER BY p.apellido, dia";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("is", $gimnasio_id, $mes);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Horas Trabajadas Profesores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h2 { color: gold; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; background-color: #111; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background-color: #222; color: gold; }
        input[type="month"], input[type="submit"] {
            padding: 8px; margin: 10px 0; font-size: 14px;
            background-color: gold; border: none; color: black;
        }
        .no-data { margin-top: 20px; color: orange; }
    </style>
</head>
<body>
    <h2>📅 Reporte de Horas Trabajadas - <?= date('F Y', strtotime($mes . "-01")) ?></h2>

    <form method="get">
        <label for="mes">Seleccionar mes:</label>
        <input type="month" name="mes" id="mes" value="<?= $mes ?>">
        <input type="submit" value="Filtrar">
    </form>

    <?php if ($resultado->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Día</th>
                <th>Horas trabajadas</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['apellido']) ?></td>
                <td><?= htmlspecialchars($row['nombre']) ?></td>
                <td><?= htmlspecialchars($row['dia']) ?></td>
                <td><?= htmlspecialchars($row['horas_totales']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="no-data">⚠️ No hay registros de asistencia para este mes.</p>
    <?php endif; ?>
</body>
</html>
