<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$es_admin = ($_SESSION['rol'] ?? '') === 'admin';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Filtro por mes y año
$anio = $_GET['anio'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');
$mes_actual = "$anio-$mes";

// Consulta principal
$sql = "
    SELECT 
        p.id AS profesor_id,
        p.apellido,
        p.nombre,
        t.monto_por_hora,
        a.id AS asistencia_id,
        a.fecha,
        a.hora_entrada,
        a.hora_salida,
        a.alumnos
    FROM profesores p
    LEFT JOIN tarifas_profesor t ON p.id = t.profesor_id
    LEFT JOIN asistencias_profesores a 
        ON a.profesor_id = p.id 
        AND DATE_FORMAT(a.fecha, '%Y-%m') = '$mes_actual'
    WHERE " . ($es_admin ? "1" : "p.gimnasio_id = $gimnasio_id") . "
    ORDER BY p.apellido, a.fecha
";

$res = $conexion->query($sql);
$datos = [];

while ($row = $res->fetch_assoc()) {
    $id = $row['profesor_id'];
    $nombre = $row['apellido'] . ' ' . $row['nombre'];
    $monto = floatval($row['monto_por_hora'] ?? 0);
    $alumnos = intval($row['alumnos'] ?? 0);

    if (!isset($datos[$id])) {
        $datos[$id] = [
            'nombre' => $nombre,
            'monto_por_hora' => $monto,
            'minutos_totales' => 0,
            'alumnos_total' => 0,
            'asistencias' => []
        ];
    }

    if ($row['hora_entrada'] && $row['hora_salida']) {
        $inicio = strtotime($row['hora_entrada']);
        $fin = strtotime($row['hora_salida']);
        $minutos = ($fin - $inicio) / 60;
        $datos[$id]['minutos_totales'] += $minutos;
        $datos[$id]['alumnos_total'] += $alumnos;

        $datos[$id]['asistencias'][] = [
            'asistencia_id' => $row['asistencia_id'],
            'fecha' => $row['fecha'],
            'hora_entrada' => $row['hora_entrada'],
            'hora_salida' => $row['hora_salida'],
            'alumnos' => $alumnos,
            'minutos' => $minutos
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pagos a Profesores</title>
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial;
            padding: 20px;
        }
        h2 { text-align: center; color: white; }
        form { text-align: center; margin-bottom: 20px; }
        select, button {
            padding: 5px 10px;
            font-size: 16px;
        }
        table {
            width: 100%;
            background: #111;
            color: gold;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #444;
            text-align: center;
        }
        th { background: #222; }
        .btn-edit {
            background: gold;
            color: black;
            padding: 4px 8px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<h2>📊 Reporte de Pagos – <?= strtoupper(date('F', mktime(0, 0, 0, $mes, 1))) . " $anio" ?></h2>

<form method="get">
    <label>Mes: 
        <select name="mes">
            <?php for ($i = 1; $i <= 12; $i++): 
                $val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                <option value="<?= $val ?>" <?= $mes == $val ? 'selected' : '' ?>><?= ucfirst(date('F', mktime(0,0,0,$i,1))) ?></option>
            <?php endfor; ?>
        </select>
    </label>
    <label>Año: 
        <select name="anio">
            <?php
            $anio_actual = date('Y');
            for ($y = $anio_actual - 3; $y <= $anio_actual + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $anio == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </label>
    <button type="submit">Filtrar</button>
</form>

<table>
    <tr>
        <th>Profesor</th>
        <th>Horas Totales</th>
        <th>Valor Hora</th>
        <th>Alumnos</th>
        <th>Total a Pagar</th>
    </tr>
    <?php foreach ($datos as $id => $prof): 
        $horas = round($prof['minutos_totales'] / 60, 2);
        $pago = round($horas * $prof['monto_por_hora'], 2);
    ?>
    <tr>
        <td><?= $prof['nombre'] ?></td>
        <td><?= $horas ?></td>
        <td>$<?= number_format($prof['monto_por_hora'], 2) ?></td>
        <td><?= $prof['alumnos_total'] ?></td>
        <td><strong>$<?= number_format($pago, 2) ?></strong></td>
    </tr>
    <?php foreach ($prof['asistencias'] as $asis): ?>
    <tr>
        <td colspan="2">📅 <?= $asis['fecha'] ?> (<?= round($asis['minutos']/60, 2) ?> hs)</td>
        <td><?= $asis['hora_entrada'] ?> - <?= $asis['hora_salida'] ?></td>
        <td><?= $asis['alumnos'] ?></td>
        <td>
            <a class="btn-edit" href="editar_horas_profesor.php?id=<?= $asis['asistencia_id'] ?>">Editar horas</a>
            <a class="btn-edit" href="editar_alumnos_asistencia.php?id=<?= $asis['asistencia_id'] ?>">Editar alumnos</a>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php endforeach; ?>
</table>

</body>
</html>
