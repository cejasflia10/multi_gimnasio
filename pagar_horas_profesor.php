<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Habilitar la visualización de errores para depuración (QUITAR EN PRODUCCIÓN)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mes_actual = date('m');
$anio_actual = date('Y');

// --- DEBUGGING TEMPORAL CRUCIAL ---
echo "<h2>DEBUG: Variables de Configuración</h2>";
echo "Gimnasio ID de Sesión (pagar_horas_profesor): <b>" . $gimnasio_id . "</b><br>";
echo "Mes Actual (MM): <b>" . $mes_actual . "</b><br>";
echo "Año Actual (YYYY): <b>" . $anio_actual . "</b><br>";
echo "Fecha Actual del Servidor: <b>" . date('Y-m-d H:i:s') . "</b><br>";
echo "<hr>";
// --- FIN DEBUGGING TEMPORAL CRUCIAL ---

// Función para calcular el monto según la cantidad de alumnos
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
    if ($f = $res->fetch_assoc()) {
        $stmt->close();
        return floatval($f['precio']);
    }
    $stmt->close();
    return 0;
}

$datos = []; // Array para almacenar los datos finales por profesor

// Consulta principal para obtener TODAS las asistencias de profesores en el mes/año
$sql_profesores_query = "
    SELECT a.id, p.id AS profesor_id, p.apellido, p.nombre, a.fecha, a.hora_ingreso, a.hora_salida AS hora_egreso
    FROM asistencias_profesores a
    JOIN profesores p ON a.profesor_id = p.id
    WHERE MONTH(a.fecha) = $mes_actual
      AND YEAR(a.fecha) = $anio_actual
      AND a.gimnasio_id = $gimnasio_id
    ORDER BY p.apellido, a.fecha, a.hora_ingreso
";

// --- DEBUGGING TEMPORAL CRUCIAL ---
echo "<h2>DEBUG: Consulta SQL de Profesores</h2>";
echo "Consulta a ejecutar: <pre>" . htmlspecialchars($sql_profesores_query) . "</pre><br>";
// --- FIN DEBUGGING TEMPORAL CRUCIAL ---

$query = $conexion->query($sql_profesores_query);

// Si la consulta falla, imprime el error para depuración
if (!$query) {
    die("Error en la consulta de asistencias de profesores: " . $conexion->error);
}

// --- DEBUGGING TEMPORAL CRUCIAL ---
$num_profesores_encontrados = $query->num_rows;
echo "Número de turnos de profesores encontrados: <b>" . $num_profesores_encontrados . "</b><br>";
echo "<hr>";
// --- FIN DEBUGGING TEMPORAL CRUCIAL ---

while ($row = $query->fetch_assoc()) {
    $profesor_id = $row['profesor_id'];
    $fecha = $row['fecha'];
    $id_asistencia = $row['id'];
    $hora_ini = !empty($row['hora_ingreso']) ? $row['hora_ingreso'] : '00:00:00';
    $hora_fin = !empty($row['hora_egreso']) ? $row['hora_egreso'] : '23:59:59'; // Si no hay hora de egreso, se asume hasta fin del día

    // Inicializar el array del profesor si aún no existe
    if (!isset($datos[$profesor_id])) {
        $datos[$profesor_id] = [
            'nombre' => $row['apellido'] . ' ' . $row['nombre'],
            'fechas' => []
        ];
    }

    // Buscar alumnos que ingresaron en el mismo horario Y CON EL MISMO PROFESOR
    // ¡¡¡CAMBIO CLAVE AQUÍ: FILTRAMOS por profesor_id en la tabla 'asistencias'!!!
    $stmt = $conexion->prepare("
        SELECT COUNT(DISTINCT cliente_id) AS total
        FROM asistencias
        WHERE fecha = ?
          AND hora BETWEEN ? AND ?
          AND gimnasio_id = ?
          AND profesor_id = ? -- ¡¡¡NUEVA CONDICIÓN CRUCIAL!!!
    ");
    if (false === $stmt) {
        die("Error al preparar la consulta de alumnos: " . $conexion->error);
    }

    $stmt->bind_param("sssii", $fecha, $hora_ini, $hora_fin, $gimnasio_id, $profesor_id); // Añadimos 'i' para el nuevo profesor_id
    $stmt->execute();
    $res = $stmt->get_result();
    $alumnos = $res->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    $monto_unitario = calcular_monto($conexion, $gimnasio_id, $alumnos);
    $monto_total = $alumnos * $monto_unitario;

    $horas = 0;
    if (!empty($row['hora_ingreso']) && !empty($row['hora_egreso'])) {
        $timestamp_ingreso = strtotime($row['hora_ingreso']);
        $timestamp_egreso = strtotime($row['hora_egreso']);
        if ($timestamp_ingreso !== false && $timestamp_egreso !== false) {
             if ($timestamp_egreso > $timestamp_ingreso) {
                $horas = round(($timestamp_egreso - $timestamp_ingreso) / 3600, 2);
            }
        }
    }

    // Añadir cada bloque de asistencia del profesor como una entrada separada
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
        <p>No se encontraron asistencias de profesores para este mes (<?= $mes_actual ?>/<?= $anio_actual ?>) en el gimnasio ID <?= $gimnasio_id ?>.</p>
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
                <?php $total_general_profesor = 0; ?>
                <?php foreach ($prof['fechas'] as $f): ?>
                    <?php $total_general_profesor += $f['monto_total']; ?>
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
                        <th colspan="6">Total a pagar a este profesor</th>
                        <th colspan="3">$<?= number_format($total_general_profesor, 2, ',', '.') ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endforeach; ?>

    <a href="index.php" class="boton">Volver al menú</a>
</div>
</body>
</html>