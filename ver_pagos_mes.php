<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Filtro por mes y año
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

// Obtener pagos del mes
$query = $conexion->query("
    SELECT m.fecha_inicio, m.fecha_vencimiento, m.metodo_pago,
           IFNULL(m.otros_pagos, 0) AS otros_pagos,
           IFNULL(m.total, 0) AS total,
           c.apellido, c.nombre
    FROM membresias m
    INNER JOIN clientes c ON m.cliente_id = c.id
    WHERE MONTH(m.fecha_inicio) = $mes AND YEAR(m.fecha_inicio) = $anio
      AND m.gimnasio_id = $gimnasio_id
    ORDER BY m.fecha_inicio DESC
");

$pagos = [];
$total_mes = 0;
while ($fila = $query->fetch_assoc()) {
    $pagos[] = $fila;
    $total_mes += floatval($fila['total'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos del Mes</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 style="text-align:center;">💳 Pagos del Mes</h2>

    <form method="get" style="text-align:center; margin-bottom:20px;">
        <label>Mes:</label>
        <select name="mes">
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $mes == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                    <?= date('F', mktime(0, 0, 0, $i, 10)) ?>
                </option>
            <?php endfor; ?>
        </select>

        <label>Año:</label>
        <select name="anio">
            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $anio == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit">Filtrar</button>
    </form>

    <table border="1">
        <tr>
            <th>Cliente</th>
            <th>Fecha Inicio</th>
            <th>Vencimiento</th>
            <th>Método Pago</th>
            <th>Otros Pagos</th>
            <th>Total ($)</th>
        </tr>
        <?php foreach ($pagos as $fila): ?>
            <tr>
                <td><?= $fila['apellido'] . ' ' . $fila['nombre'] ?></td>
                <td><?= $fila['fecha_inicio'] ?></td>
                <td><?= $fila['fecha_vencimiento'] ?></td>
                <td><?= ucfirst($fila['metodo_pago'] ?? '') ?></td>
                <td>$<?= number_format($fila['otros_pagos'], 0, ',', '.') ?></td>
                <td>$<?= number_format($fila['total'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h3 style="text-align:right; margin-top:20px;">💰 Total del mes: $<?= number_format($total_mes, 0, ',', '.') ?></h3>
</div>
</body>
</html>
