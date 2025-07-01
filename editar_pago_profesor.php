<?php
session_start();
include 'conexion.php';

$mes_actual = date('m');
$anio_actual = date('Y');

// Obtener lista de profesores
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores");

function calcularPago($conexion, $profesor_id, $mes, $anio) {
    $pagos = [];

    $asistencias = $conexion->query("
        SELECT fecha, hora_ingreso
        FROM asistencias_profesor
        WHERE profesor_id = $profesor_id
          AND MONTH(fecha) = $mes
          AND YEAR(fecha) = $anio
    ");

    while ($asis = $asistencias->fetch_assoc()) {
        $fecha = $asis['fecha'];

        // Cantidad de alumnos ese día con este profesor
        $alumnos_q = $conexion->query("
            SELECT COUNT(*) AS cantidad
            FROM asistencias_clientes
            WHERE profesor_id = $profesor_id AND fecha = '$fecha'
        ");
        $cantidad = $alumnos_q->fetch_assoc()['cantidad'];

        // Cálculo de pago
        if ($cantidad >= 5) {
            $monto = 2000;
        } elseif ($cantidad >= 2) {
            $monto = 1500;
        } elseif ($cantidad == 1) {
            $monto = 1000;
        } else {
            $monto = 0;
        }

        $pagos[] = [
            'fecha' => $fecha,
            'alumnos' => $cantidad,
            'monto' => $monto
        ];
    }

    return $pagos;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos a Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h2 { color: gold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #111; }
        th, td { padding: 10px; border: 1px solid #555; text-align: center; }
        th { background: #222; }
        .total { font-weight: bold; color: #0f0; }
    </style>
</head>
<body>

<h2>Pagos a Profesores - <?= date('F Y') ?></h2>

<?php while ($prof = $profesores->fetch_assoc()): ?>
    <?php
        $pagos = calcularPago($conexion, $prof['id'], $mes_actual, $anio_actual);
        if (count($pagos) == 0) continue;

        $total_profesor = array_sum(array_column($pagos, 'monto'));
    ?>
    <h3><?= $prof['apellido'] . ', ' . $prof['nombre'] ?></h3>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Alumnos</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><?= $p['fecha'] ?></td>
                    <td><?= $p['alumnos'] ?></td>
                    <td>$<?= number_format($p['monto'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="2" class="total">TOTAL A PAGAR</td>
                <td class="total">$<?= number_format($total_profesor, 0, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>
<?php endwhile; ?>

</body>
</html>
