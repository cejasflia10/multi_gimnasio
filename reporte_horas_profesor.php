<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$profesor_id = $_GET['profesor_id'] ?? '';

$where_profesor = $profesor_id ? "AND p.id = $profesor_id" : '';

$query = "
    SELECT 
        p.id AS profesor_id,
        CONCAT(p.apellido, ' ', p.nombre) AS profesor,
        a.fecha,
        a.hora_ingreso,
        a.hora_egreso,
        TIMESTAMPDIFF(MINUTE, a.hora_ingreso, a.hora_egreso)/60 AS horas_trabajadas
    FROM asistencias_profesores a
    INNER JOIN profesores p ON a.profesor_id = p.id
    WHERE a.gimnasio_id = $gimnasio_id
      AND MONTH(a.fecha) = $mes
      AND YEAR(a.fecha) = $anio
      $where_profesor
    ORDER BY p.apellido, a.fecha
";

$resultado = $conexion->query($query);

$profesores = [];
while ($row = $resultado->fetch_assoc()) {
    $id = $row['profesor_id'];
    $horas = round(floatval($row['horas_trabajadas']), 2);
    if (!isset($profesores[$id])) {
        $profesores[$id] = [
            'nombre' => $row['profesor'],
            'total_horas' => 0,
            'detalle' => []
        ];
    }
    $profesores[$id]['total_horas'] += $horas;
    $profesores[$id]['detalle'][] = [
        'fecha' => $row['fecha'],
        'ingreso' => $row['hora_ingreso'],
        'egreso' => $row['hora_egreso'],
        'horas' => $horas
    ];
}

// Obtener tarifas por rango de alumnos (precio_hora)
$tarifas = [];
$tarifa_q = $conexion->query("SELECT * FROM precio_hora WHERE gimnasio_id = $gimnasio_id");
while ($t = $tarifa_q->fetch_assoc()) {
    $tarifas[] = $t;
}

function calcular_monto($horas, $cantidad_alumnos, $tarifas) {
    foreach ($tarifas as $t) {
        if ($cantidad_alumnos >= $t['rango_min'] && $cantidad_alumnos <= $t['rango_max']) {
            return $horas * floatval($t['precio']);
        }
    }
    return 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Horas Trabajadas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #000; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid gold; padding: 8px; text-align: center; }
        select, input[type="submit"] { padding: 6px; margin-right: 10px; }
    </style>
</head>
<body>
<h1>🕘 Reporte de Horas Trabajadas por Profesores</h1>

<form method="get">
    <label>Mes:
        <select name="mes">
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= $i ?>" <?= $mes == $i ? 'selected' : '' ?>><?= $i ?></option>
            <?php endfor; ?>
        </select>
    </label>
    <label>Año:
        <select name="anio">
            <?php for ($a = 2024; $a <= date('Y'); $a++): ?>
                <option value="<?= $a ?>" <?= $anio == $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endfor; ?>
        </select>
    </label>
    <label>Profesor:
        <select name="profesor_id">
            <option value="">Todos</option>
            <?php
            $profs = $conexion->query("SELECT id, CONCAT(apellido, ' ', nombre) AS nombre FROM profesores WHERE gimnasio_id = $gimnasio_id");
            while ($p = $profs->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>" <?= $profesor_id == $p['id'] ? 'selected' : '' ?>><?= $p['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
    </label>
    <input type="submit" value="Filtrar">
</form>

<?php foreach ($profesores as $id => $datos): ?>
    <h2><?= $datos['nombre'] ?> - Total Horas: <?= number_format($datos['total_horas'], 2) ?> hs</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Ingreso</th>
                <th>Egreso</th>
                <th>Horas</th>
                <th>Cantidad Alumnos</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $total_monto = 0;
        foreach ($datos['detalle'] as $dia):
            $fecha = $dia['fecha'];
            // Consultar alumnos registrados ese día para ese profesor
            $alumnos_q = $conexion->query("SELECT COUNT(*) AS cantidad FROM reservas r JOIN turnos t ON r.turno_id = t.id WHERE t.id_profesor = $id AND r.fecha = '$fecha'");
            $alumnos = $alumnos_q->fetch_assoc()['cantidad'] ?? 0;
            $monto = calcular_monto($dia['horas'], $alumnos, $tarifas);
            $total_monto += $monto;
        ?>
            <tr>
                <td><?= $fecha ?></td>
                <td><?= $dia['ingreso'] ?></td>
                <td><?= $dia['egreso'] ?></td>
                <td><?= number_format($dia['horas'], 2) ?></td>
                <td><?= $alumnos ?></td>
                <td>$<?= number_format($monto, 2) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr style="font-weight: bold; background: #111;">
            <td colspan="5">TOTAL A PAGAR</td>
            <td>$<?= number_format($total_monto, 2) ?></td>
        </tr>
        </tbody>
    </table>
<?php endforeach; ?>

</body>
</html>
