<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

// Obtener profesores del gimnasio
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id");
function calcular_monto($conexion, $profesor_id, $gimnasio_id, $horas, $alumnos) {
    $tarifa = $conexion->query("
        SELECT monto_por_hora, valor_hora, modo_pago, porcentaje_1, porcentaje_2, porcentaje_3 
        FROM tarifas_profesor 
        WHERE profesor_id = $profesor_id AND gimnasio_id = $gimnasio_id
    ")->fetch_assoc();

    if (!$tarifa) return 0;

    $modo = $tarifa['modo_pago'];

    if ($modo === 'fijo') {
        return $horas * $tarifa['monto_por_hora'];
    } elseif ($modo === 'asistencia') {
        if ($alumnos <= 5) {
            return $horas * $tarifa['valor_hora'] * ($tarifa['porcentaje_1'] / 100);
        } elseif ($alumnos <= 10) {
            return $horas * $tarifa['valor_hora'] * ($tarifa['porcentaje_2'] / 100);
        } else {
            return $horas * $tarifa['valor_hora'] * ($tarifa['porcentaje_3'] / 100);
        }
    }

    return 0;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Horas de Profesores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>⏱ Reporte de Horas por Profesor</h2>

    <form method="get">
        <label>Mes:
            <input type="number" name="mes" value="<?= $mes ?>" min="1" max="12">
        </label>
        <label>Año:
            <input type="number" name="anio" value="<?= $anio ?>" min="2020">
        </label>
        <button type="submit">Filtrar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Profesor</th>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Horas</th>
                <th>Alumnos</th>
                <th>Total $</th>
            </tr>
        </thead>
        <tbody>
        <?php
        while ($p = $profesores->fetch_assoc()) {
            $id_profesor = $p['id'];
            $nombre = $p['apellido'] . ' ' . $p['nombre'];

            $total_horas = 0;
            $total_monto = 0;

            $asistencias = $conexion->query("
                SELECT id, fecha, hora_entrada, hora_salida
                FROM asistencias_profesores
                WHERE profesor_id = $id_profesor 
                  AND MONTH(fecha) = $mes AND YEAR(fecha) = $anio
                  AND hora_entrada IS NOT NULL AND hora_salida IS NOT NULL
                  AND gimnasio_id = $gimnasio_id
                ORDER BY fecha, hora_entrada
            ");

            while ($f = $asistencias->fetch_assoc()) {
                $fecha = $f['fecha'];
                $entrada = $f['hora_entrada'];
                $salida  = $f['hora_salida'];

                // Calcular horas trabajadas
                $inicio = new DateTime($entrada);
                $fin = new DateTime($salida);
                $intervalo = $inicio->diff($fin);
                $horas = $intervalo->h + ($intervalo->i / 60);

                // Obtener cantidad de alumnos en ese horario
                $q = $conexion->query("
                    SELECT COUNT(*) AS cantidad
                    FROM asistencias
                    WHERE fecha = '$fecha'
                      AND hora BETWEEN '$entrada' AND '$salida'
                      AND gimnasio_id = $gimnasio_id
                ");
                $alumnos = ($q && $a = $q->fetch_assoc()) ? $a['cantidad'] : 0;

                // Calcular monto
                $monto = calcular_monto($conexion, $id_profesor, $gimnasio_id, $horas, $alumnos);

                // Acumulado
                $total_horas += $horas;
                $total_monto += $monto;

                echo "<tr>
                    <td>$nombre</td>
                    <td>$fecha</td>
                    <td>$entrada</td>
                    <td>$salida</td>
                    <td>" . number_format($horas, 2) . "</td>
                    <td>$alumnos</td>
                    <td>$" . number_format($monto, 2) . "</td>
                </tr>";
            }

            echo "<tr style='font-weight:bold; background:#222;'>
                <td colspan='4'>Total de $nombre</td>
                <td>" . number_format($total_horas, 2) . "</td>
                <td>-</td>
                <td>$" . number_format($total_monto, 2) . "</td>
            </tr>";
        }
        ?>
        </tbody>
    </table>
</div>
</body>
</html>
