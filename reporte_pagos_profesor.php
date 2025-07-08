<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mes_actual = date('Y-m');
$es_admin = ($_SESSION['rol'] ?? '') === 'admin';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$sql = "
    SELECT 
        p.id AS profesor_id,
        p.apellido,
        p.nombre,
        t.monto_por_hora,
        a.fecha,
        a.hora_ingreso,
        a.hora_salida
    FROM profesores p
    LEFT JOIN tarifas_profesor t ON p.id = t.profesor_id
    LEFT JOIN asistencias_profesores a ON a.profesor_id = p.id AND DATE_FORMAT(a.fecha, '%Y-%m') = '$mes_actual'
    WHERE " . ($es_admin ? "1" : "p.gimnasio_id = $gimnasio_id") . "
    ORDER BY p.apellido, a.fecha
";

$res = $conexion->query($sql);

// Agrupar por profesor
$datos = [];

while ($row = $res->fetch_assoc()) {
    $id = $row['profesor_id'];
    $nombre = $row['apellido'] . ' ' . $row['nombre'];
    $monto = floatval($row['monto_por_hora'] ?? 0);

    if (!isset($datos[$id])) {
        $datos[$id] = [
            'nombre' => $nombre,
            'monto_por_hora' => $monto,
            'minutos_totales' => 0,
        ];
    }

    if ($row['hora_ingreso'] && $row['hora_salida']) {
        $inicio = strtotime($row['hora_ingreso']);
        $fin = strtotime($row['hora_salida']);
        $minutos = ($fin - $inicio) / 60;
        $datos[$id]['minutos_totales'] += $minutos;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Horas y Pagos a Profesores</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial;
            padding: 20px;
        }
        h2 { text-align: center; color: white; }
        table {
            width: 100%;
            background-color: #111;
            color: gold;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #444;
            text-align: center;
        }
        th {
            background-color: #222;
        }
    </style>
</head>
<body>

<h2>📊 Reporte de Horas Trabajadas y Pagos – <?= date('F Y') ?></h2>

<table>
    <tr>
        <th>Profesor</th>
        <th>Horas trabajadas</th>
        <th>Valor por hora ($)</th>
        <th>Total a pagar ($)</th>
    </tr>
    <?php foreach ($datos as $prof): 
        $horas = round($prof['minutos_totales'] / 60, 2);
        $pago = round($horas * $prof['monto_por_hora'], 2);
    ?>
        <tr>
            <td><?= $prof['nombre'] ?></td>
            <td><?= $horas ?></td>
            <td><?= number_format($prof['monto_por_hora'], 2) ?></td>
            <td><strong>$<?= number_format($pago, 2) ?></strong></td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
