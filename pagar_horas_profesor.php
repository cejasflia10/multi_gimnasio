<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mes_actual = date('m');
$anio_actual = date('Y');

function calcular_monto($conexion, $gimnasio_id, $alumnos) {
    $stmt = $conexion->prepare("
        SELECT precio FROM precio_hora
        WHERE gimnasio_id = ?
          AND ? BETWEEN rango_min AND rango_max
        LIMIT 1
    ");
    $stmt->bind_param("ii", $gimnasio_id, $alumnos);
    $stmt->execute();
    $res = $stmt->get_result();
    $precio = $res->fetch_assoc()['precio'] ?? 0;
    $stmt->close();
    return floatval($precio);
}

$datos = [];

$sql = "
    SELECT a.id, p.id AS profesor_id, p.apellido, p.nombre, a.fecha, a.hora_ingreso, a.hora_salida AS hora_egreso
    FROM asistencias_profesores a
    JOIN profesores p ON a.profesor_id = p.id
    WHERE MONTH(a.fecha) = $mes_actual
      AND YEAR(a.fecha) = $anio_actual
      AND a.gimnasio_id = $gimnasio_id
    ORDER BY p.apellido, a.fecha, a.hora_ingreso
";

$query = $conexion->query($sql);
if (!$query) {
    die("Error en la consulta: " . $conexion->error);
}

while ($row = $query->fetch_assoc()) {
    $profesor_id = $row['profesor_id'];
    $fecha = $row['fecha'];
    $id_asistencia = $row['id'];
    $hora_ini = !empty($row['hora_ingreso']) ? $row['hora_ingreso'] : '00:00:00';
    $hora_fin = !empty($row['hora_egreso']) ? $row['hora_egreso'] : '23:59:59';

    if (!isset($datos[$profesor_id])) {
        $datos[$profesor_id] = [
            'nombre' => $row['apellido'] . ' ' . $row['nombre'],
            'fechas' => []
        ];
    }

    $stmt = $conexion->prepare("
        SELECT COUNT(DISTINCT cliente_id) AS total
        FROM asistencias
        WHERE fecha = ?
          AND hora BETWEEN ? AND ?
          AND gimnasio_id = ?
          AND profesor_id = ?
    ");
    $stmt->bind_param("sssii", $fecha, $hora_ini, $hora_fin, $gimnasio_id, $profesor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $alumnos = $res->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    $monto_unitario = calcular_monto($conexion, $gimnasio_id, $alumnos);
$monto_total = $monto_unitario;

    // Calcular horas
    $horas = 1; // por defecto 1 hora si no hay salida
    if (!empty($row['hora_ingreso']) && !empty($row['hora_egreso'])) {
        $t1 = strtotime($row['hora_ingreso']);
        $t2 = strtotime($row['hora_egreso']);
        if ($t1 !== false && $t2 !== false && $t2 > $t1) {
            $horas = round(($t2 - $t1) / 3600, 2);
        }
    }

    $datos[$profesor_id]['fechas'][] = [
        'id_asistencia' => $id_asistencia,
        'fecha' => $fecha,
        'ingreso' => $row['hora_ingreso'] ?? '-',
        'egreso' => $row['hora_egreso'] ?? '-',
        'horas' => $horas,
        'alumnos' => $alumnos,
        'monto_unitario' => $monto_unitario,
        'monto_total' => $monto_total
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagar Horas Profesor</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>💰 Pago de Horas a Profesores</h2>

    <?php if (empty($datos)): ?>
        <p>No se encontraron asistencias para este mes (<?= $mes_actual ?>/<?= $anio_actual ?>).</p>
    <?php endif; ?>

    <?php foreach ($datos as $prof): ?>
        <div class="tarjeta">
            <h3><?= $prof['nombre'] ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ingreso</th>
                        <th>Egreso</th>
                        <th>Horas</th>
                        <th>Alumnos</th>
                        <th>Unitario ($)</th>
                        <th>Total ($)</th>
                        <th>✏️</th>
                        <th>🗑️</th>
                    </tr>
                </thead>
                <tbody>
                <?php $total_general = 0; ?>
                <?php foreach ($prof['fechas'] as $f): ?>
                    <?php $total_general += $f['monto_total']; ?>
                    <tr>
                        <td><?= $f['fecha'] ?></td>
                        <td><?= $f['ingreso'] ?></td>
                        <td><?= $f['egreso'] ?></td>
                        <td><?= $f['horas'] ?></td>
                        <td><?= $f['alumnos'] ?></td>
                        <td>$<?= number_format($f['monto_unitario'], 2, ',', '.') ?></td>
                        <td>$<?= number_format($f['monto_total'], 2, ',', '.') ?></td>
                        <td><a href="editar_asistencia_profesor.php?id=<?= $f['id_asistencia'] ?>" class="boton-mini">✏️</a></td>
                        <td><a href="eliminar_asistencia_profesor.php?id=<?= $f['id_asistencia'] ?>" class="boton-mini rojo" onclick="return confirm('¿Eliminar este registro?');">🗑️</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="6">Total a pagar</th>
                        <th colspan="3">$<?= number_format($total_general, 2, ',', '.') ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endforeach; ?>

    <a href="index.php" class="boton">Volver al menú</a>
</div>
</body>
</html>
