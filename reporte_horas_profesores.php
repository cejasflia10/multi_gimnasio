<?php
include 'conexion.php';
session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mes_actual = date('m');
$anio_actual = date('Y');

$query = $conexion->query("SELECT p.id, p.apellido, p.nombre, a.fecha, a.hora_ingreso, a.hora_egreso, a.monto_pagado
    FROM asistencias_profesores a
    JOIN profesores p ON a.profesor_id = p.id
    WHERE MONTH(a.fecha) = $mes_actual
      AND YEAR(a.fecha) = $anio_actual
      AND a.gimnasio_id = $gimnasio_id
    ORDER BY p.apellido, a.fecha");

$datos = [];
while ($fila = $query->fetch_assoc()) {
    $id = $fila['id'];
    if (!isset($datos[$id])) {
        $datos[$id] = [
            'nombre' => $fila['apellido'] . ' ' . $fila['nombre'],
            'registros' => [],
            'total_horas' => 0,
            'total_monto' => 0
        ];
    }

    $ingreso = strtotime($fila['hora_ingreso']);
    $egreso = strtotime($fila['hora_egreso']);
    $horas_trabajadas = ($egreso && $ingreso) ? round(($egreso - $ingreso) / 3600, 2) : 0;
    $monto = floatval($fila['monto_pagado']);

    $datos[$id]['registros'][] = [
        'fecha' => $fila['fecha'],
        'hora_ingreso' => $fila['hora_ingreso'],
        'hora_egreso' => $fila['hora_egreso'],
        'horas' => $horas_trabajadas,
        'monto' => $monto
    ];
    $datos[$id]['total_horas'] += $horas_trabajadas;
    $datos[$id]['total_monto'] += $monto;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Horas Trabajadas Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
   <div class="contenedor"> 
    <h1>Horas Trabajadas por Profesores - <?= date('F Y') ?></h1>

    <?php if (empty($datos)): ?>
        <div class="mensaje">No hay registros de horas trabajadas este mes.</div>
    <?php endif; ?>

    <?php foreach ($datos as $profesor): ?>
        <h2><?= $profesor['nombre'] ?></h2>
        <table>
            <tr>
                <th>Fecha</th>
                <th>Ingreso</th>
                <th>Egreso</th>
                <th>Horas</th>
                <th>Monto</th>
            </tr>
            <?php foreach ($profesor['registros'] as $r): ?>
                <tr>
                    <td><?= $r['fecha'] ?></td>
                    <td><?= $r['hora_ingreso'] ?></td>
                    <td><?= $r['hora_egreso'] ?></td>
                    <td><?= $r['horas'] ?> hs</td>
                    <td>$<?= number_format($r['monto'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th colspan="3">Totales del Mes</th>
                <th><?= $profesor['total_horas'] ?> hs</th>
                <th>$<?= number_format($profesor['total_monto'], 2) ?></th>
            </tr>
        </table>
    <?php endforeach; ?>
</div>
</body>
</html>
