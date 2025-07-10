<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$profesor_id = isset($_GET['profesor_id']) ? intval($_GET['profesor_id']) : 0;

// Obtener listado de profesores del gimnasio
$profesores_q = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id");
$profesores = [];
while ($row = $profesores_q->fetch_assoc()) {
    $profesores[] = $row;
}
if ($profesor_id == 0 && count($profesores) > 0) {
    $profesor_id = $profesores[0]['id'];
}

// Obtener nombre del profesor actual
$nombre_profesor = '';
foreach ($profesores as $p) {
    if ($p['id'] == $profesor_id) {
        $nombre_profesor = $p['apellido'] . ' ' . $p['nombre'];
        break;
    }
}

// Buscar días con asistencias de alumnos asignados a turnos del profesor
$turnos_q = $conexion->query("
    SELECT a.fecha, MIN(a.hora) AS hora_ingreso
    FROM asistencias a
    JOIN turnos_reservas r ON a.cliente_id = r.cliente_id AND a.fecha = r.fecha
    JOIN turnos_profesor t ON r.turno_id = t.id
    WHERE t.profesor_id = $profesor_id
      AND t.gimnasio_id = $gimnasio_id
    GROUP BY a.fecha
    ORDER BY a.fecha DESC
");

$total_pago = 0;
$filas = [];

while ($fila = $turnos_q->fetch_assoc()) {
    $fecha = $fila['fecha'];
    $hora_ingreso = $fila['hora_ingreso'];
    $hora_salida = date('H:i:s', strtotime($hora_ingreso . ' +1 hour'));

    // Contar alumnos entre el ingreso y la salida
    $alumnos_q = $conexion->query("
        SELECT COUNT(DISTINCT a.cliente_id) AS cantidad
        FROM asistencias a
        JOIN turnos_reservas r ON a.cliente_id = r.cliente_id AND a.fecha = r.fecha
        JOIN turnos_profesor t ON r.turno_id = t.id
        WHERE t.profesor_id = $profesor_id
          AND t.gimnasio_id = $gimnasio_id
          AND a.fecha = '$fecha'
          AND a.hora BETWEEN '$hora_ingreso' AND '$hora_salida'
    ");
    $cantidad_alumnos = $alumnos_q->fetch_assoc()['cantidad'] ?? 0;

    // Buscar precio por cantidad de alumnos
    $precio_q = $conexion->query("SELECT precio 
        FROM precio_hora 
        WHERE gimnasio_id = $gimnasio_id 
        AND $cantidad_alumnos BETWEEN rango_min AND rango_max 
        LIMIT 1");
    $precio = $precio_q->fetch_assoc()['precio'] ?? 0;

    $total_pago += $precio;

    $filas[] = [
        'fecha' => $fecha,
        'hora_ingreso' => $hora_ingreso,
        'hora_salida' => $hora_salida,
        'alumnos' => $cantidad_alumnos,
        'pago' => $precio
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Horas</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        form.inline { display: inline; }
        .btn { padding: 4px 10px; font-weight: bold; border-radius: 4px; text-decoration: none; }
        .editar { background: gold; color: black; }
        .eliminar { background: red; color: white; border: none; }
    </style>
</head>
<body>
<div class="contenedor">

<h2 style="text-align:center;">🕒 Reporte de Horas - <?= $nombre_profesor ?></h2>

<form method="get" style="text-align:center;">
    <label>Seleccionar Profesor:</label>
    <select name="profesor_id" onchange="this.form.submit()">
        <?php foreach ($profesores as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $p['id'] == $profesor_id ? 'selected' : '' ?>>
                <?= $p['apellido'] . ' ' . $p['nombre'] ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<table border="1">
    <tr>
        <th>Fecha</th>
        <th>Ingreso</th>
        <th>Salida</th>
        <th>Alumnos</th>
        <th>Pago ($)</th>
    </tr>
    <?php foreach ($filas as $f): ?>
        <tr>
            <td><?= $f['fecha'] ?></td>
            <td><?= $f['hora_ingreso'] ?></td>
            <td><?= $f['hora_salida'] ?></td>
            <td><?= $f['alumnos'] ?></td>
            <td>$<?= number_format($f['pago'], 0, ',', '.') ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h3 style="text-align:right; margin-top:20px;">💰 Total a pagar: $<?= number_format($total_pago, 0, ',', '.') ?></h3>

</div>
</body>
</html>
