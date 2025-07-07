<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';
include 'menu_horizontal.php';


date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$profesor_id = $_GET['profesor_id'] ?? '';

// Listado de profesores
$profesores_q = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id");

// Filtros
$condiciones = [
    "pp.gimnasio_id = $gimnasio_id",
    "pp.mes = '$anio-$mes'"
];
if (!empty($profesor_id)) {
    $condiciones[] = "p.id = $profesor_id";
}
$where = implode(" AND ", $condiciones);

// Consulta principal
$sql = "
    SELECT 
        p.apellido, 
        p.nombre, 
        SUM(pp.horas_trabajadas) AS total_horas, 
        ANY_VALUE(pp.monto_hora) AS valor_hora, 
        SUM(pp.total_pagado) AS total
    FROM pagos_profesor pp
    JOIN profesores p ON pp.profesor_id = p.id
    WHERE $where
    GROUP BY p.id
    ORDER BY p.apellido, p.nombre
";
$pagos_q = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos a Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">

<h2>📄 Pagos a Profesores</h2>

<form method="GET">
    <label>Mes:</label>
    <select name="mes">
        <?php for ($i = 1; $i <= 12; $i++): ?>
            <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $i == $mes ? 'selected' : '' ?>><?= $i ?></option>
        <?php endfor; ?>
    </select>

    <label>Año:</label>
    <select name="anio">
        <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
            <option value="<?= $y ?>" <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>

    <label>Profesor:</label>
    <select name="profesor_id">
        <option value="">Todos</option>
        <?php while ($p = $profesores_q->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" <?= $profesor_id == $p['id'] ? 'selected' : '' ?>>
                <?= $p['apellido'] . ' ' . $p['nombre'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <button type="submit">Filtrar</button>
</form>

<table>
    <thead>
        <tr>
            <th>Profesor</th>
            <th>Total Horas</th>
            <th>Valor por Hora</th>
            <th>Total a Pagar</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $pagos_q->fetch_assoc()): ?>
        <tr>
            <td><?= $fila['apellido'] . ' ' . $fila['nombre'] ?></td>
            <td><?= $fila['total_horas'] ?></td>
            <td>$<?= number_format($fila['valor_hora'], 0, ',', '.') ?></td>
            <td>$<?= number_format($fila['total'], 0, ',', '.') ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</div>
</body>
</html>
