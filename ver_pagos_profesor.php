<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$mes = date('m');
$anio = date('Y');

// Traer profesores, total horas del mes, y valor hora
$query = "
SELECT 
    p.id,
    CONCAT(p.apellido, ' ', p.nombre) AS nombre,
    COALESCE(SUM(a.horas_trabajadas), 0) AS total_horas,
    COALESCE(pp.valor_hora, 0) AS valor_hora,
    (COALESCE(SUM(a.horas_trabajadas), 0) * COALESCE(pp.valor_hora, 0)) AS total_pagar
FROM profesores p
LEFT JOIN asistencia_profesor a ON p.id = a.profesor_id AND MONTH(a.fecha) = $mes AND YEAR(a.fecha) = $anio
LEFT JOIN plan_profesor pp ON p.id = pp.profesor_id
GROUP BY p.id
ORDER BY nombre
";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos a Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #444; text-align: center; }
        th { background-color: #222; }
        tr:nth-child(even) { background-color: #1a1a1a; }
        h1 { text-align: center; }
    </style>
</head>
<body>

<h1>💰 Pagos a Profesores - <?= date('F Y') ?></h1>

<table>
    <thead>
        <tr>
            <th>Profesor</th>
            <th>Horas Trabajadas</th>
            <th>Valor x Hora ($)</th>
            <th>Total a Pagar ($)</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($fila['nombre']) ?></td>
                <td><?= number_format($fila['total_horas'], 2) ?></td>
                <td>$<?= number_format($fila['valor_hora'], 2) ?></td>
                <td><strong>$<?= number_format($fila['total_pagar'], 2) ?></strong></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
