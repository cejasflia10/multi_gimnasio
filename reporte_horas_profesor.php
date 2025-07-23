<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$mes_actual = date('m');
$anio_actual = date('Y');

// Obtener profesores del gimnasio
$profesores_q = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id");
$profesores = [];
while ($p = $profesores_q->fetch_assoc()) {
    $profesores[$p['id']] = $p['apellido'] . ' ' . $p['nombre'];
}

// Consultar asistencias de clientes por profesor
$query = $conexion->query("
    SELECT 
        p.id AS profesor_id,
        p.apellido,
        p.nombre,
        a.fecha,
        MIN(a.hora) AS hora_inicio,
        MAX(a.hora) AS hora_fin,
        COUNT(DISTINCT a.cliente_id) AS cantidad_alumnos
    FROM asistencias a
    JOIN profesores p ON a.profesor_id = p.id
    WHERE MONTH(a.fecha) = $mes_actual
      AND YEAR(a.fecha) = $anio_actual
      AND a.gimnasio_id = $gimnasio_id
    GROUP BY p.id, a.fecha
    ORDER BY p.apellido, a.fecha
");

$datos = [];
while ($fila = $query->fetch_assoc()) {
    $id = $fila['profesor_id'];
    $fecha = $fila['fecha'];
    $inicio = $fila['hora_inicio'];
    $fin = $fila['hora_fin'];

    if (!isset($datos[$id])) {
        $datos[$id] = [
            'nombre' => $fila['apellido'] . ' ' . $fila['nombre'],
            'fechas' => []
        ];
    }

    // Calcular duración en horas
    if ($inicio && $fin) {
        $horas_trabajadas = round((strtotime($fin) - strtotime($inicio)) / 3600, 2);
    } else {
        $horas_trabajadas = 0;
    }

    $alumnos = $fila['cantidad_alumnos'];
    $monto = calcular_monto($conexion, $gimnasio_id, $alumnos);

    $datos[$id]['fechas'][] = [
        'fecha' => $fecha,
        'ingreso' => $inicio,
        'egreso' => $fin,
        'horas' => $horas_trabajadas,
        'alumnos' => $alumnos,
        'monto' => $monto
    ];
}

// Función para calcular monto según cantidad de alumnos
function calcular_monto($conexion, $gimnasio_id, $alumnos) {
    $q = $conexion->query("
        SELECT precio FROM precio_hora
        WHERE gimnasio_id = $gimnasio_id
          AND $alumnos BETWEEN rango_min AND rango_max
        LIMIT 1
    ");
    if ($fila = $q->fetch_assoc()) {
        return $fila['precio'];
    }
    return 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Horas Profesores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>🕒 Reporte de Horas y Pago a Profesores</h2>

    <?php if (empty($datos)): ?>
        <p>No se encontraron asistencias registradas para este mes.</p>
    <?php endif; ?>

    <?php foreach ($datos as $prof_id => $info): ?>
        <div class="tarjeta">
            <h3><?= $info['nombre'] ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ingreso</th>
                        <th>Egreso</th>
                        <th>Horas</th>
                        <th>Alumnos</th>
                        <th>Monto $</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $total_monto = 0;
                foreach ($info['fechas'] as $f):
                    $total_monto += $f['monto'];
                ?>
                    <tr>
                        <td><?= $f['fecha'] ?></td>
                        <td><?= $f['ingreso'] ?></td>
                        <td><?= $f['egreso'] ?></td>
                        <td><?= $f['horas'] ?></td>
                        <td><?= $f['alumnos'] ?></td>
                        <td>$<?= number_format($f['monto'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5">Total a pagar</th>
                        <th>$<?= number_format($total_monto, 2, ',', '.') ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endforeach; ?>

    <a href="index.php" class="boton">Volver al menú</a>
</div>
</body>
</html>
