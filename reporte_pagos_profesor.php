<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
include 'menu_horizontal.php';

$es_admin = ($_SESSION['rol'] ?? '') === 'admin';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Filtro por mes y año
$anio = $_GET['anio'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');
$mes_actual = "$anio-$mes";

// Consulta
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
    LEFT JOIN asistencias_profesores a 
        ON a.profesor_id = p.id 
        AND DATE_FORMAT(a.fecha, '%Y-%m') = '$mes_actual'
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
        form {
            text-align: center;
            margin-bottom: 20px;
        }
        select {
            padding: 5px;
            font-size: 16px;
        }
        button {
            padding: 6px 12px;
            background-color: gold;
            color: black;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        table {
            width: 100%;
            background-color: #111;
            color: gold;
            border-collapse: collapse;
            margin-top: 10px;
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

<h2>📊 Reporte de Horas y Pagos – <?= strtoupper(date('F', mktime(0,0,0,$mes,1))) . " $anio" ?></h2>

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
