<?php
include 'conexion.php';
session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mes_actual = date('m');
$anio_actual = date('Y');

// Proceso de eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    $id = intval($_POST['eliminar_id']);
    $stmt = $conexion->prepare("DELETE FROM asistencias_profesores WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param('ii', $id, $gimnasio_id);
    $stmt->execute();
    echo "<p style='color:lime; text-align:center;'>✅ Turno eliminado correctamente.</p>";
}

// Obtener asistencias del mes actual
$stmt = $conexion->prepare('
    SELECT a.id, p.id AS profesor_id, p.apellido, p.nombre, a.fecha, a.hora_ingreso, a.hora_egreso,
           TIMESTAMPDIFF(MINUTE, a.hora_ingreso, a.hora_egreso) AS minutos_trabajados
    FROM asistencias_profesores a
    JOIN profesores p ON a.profesor_id = p.id
    WHERE MONTH(a.fecha) = ? AND YEAR(a.fecha) = ? AND a.gimnasio_id = ?
    ORDER BY p.apellido, a.fecha
');
$stmt->bind_param('iii', $mes_actual, $anio_actual, $gimnasio_id);
$stmt->execute();
$query = $stmt->get_result();

$datos = [];
while ($fila = $query->fetch_assoc()) {
    $id = $fila['profesor_id'];
    if (!isset($datos[$id])) {
        $datos[$id] = [
            'nombre' => $fila['apellido'] . ' ' . $fila['nombre'],
            'total_minutos' => 0,
            'asistencias' => []
        ];
    }

    // Consultar alumnos durante el turno
    $alumnos_q = $conexion->prepare("
        SELECT COUNT(*) AS cantidad
        FROM asistencias
        WHERE fecha = ? AND hora BETWEEN ? AND ? AND gimnasio_id = ?
    ");
    $alumnos_q->bind_param("sssi", $fila['fecha'], $fila['hora_ingreso'], $fila['hora_egreso'], $gimnasio_id);
    $alumnos_q->execute();
    $alumnos_res = $alumnos_q->get_result()->fetch_assoc();
    $cantidad_alumnos = $alumnos_res['cantidad'] ?? 0;

    $datos[$id]['total_minutos'] += intval($fila['minutos_trabajados']);
    $datos[$id]['asistencias'][] = [
        'id' => $fila['id'],
        'fecha' => $fila['fecha'],
        'ingreso' => $fila['hora_ingreso'],
        'egreso' => $fila['hora_egreso'],
        'minutos' => intval($fila['minutos_trabajados']),
        'alumnos' => $cantidad_alumnos
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Horas Trabajadas</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; }
        h2 { text-align: center; margin-top: 20px; }
        table { width: 95%; margin: 20px auto; border-collapse: collapse; background-color: #222; }
        th, td { padding: 10px; border: 1px solid gold; color: white; text-align: center; }
        .subtitulo { background-color: #333; font-weight: bold; }
        .boton {
            padding: 5px 10px; border: none; border-radius: 5px;
            font-weight: bold; cursor: pointer; text-decoration: none;
        }
        .editar { background-color: gold; color: black; }
        .eliminar { background-color: red; color: white; }
        .agregar {
            background-color: lime; color: black;
            margin: 20px auto; display: block;
            text-align: center; width: fit-content;
        }
        form.inline { display: inline; }
    </style>
</head>
<body>
<h2>📋 Reporte de Horas Trabajadas - Mes Actual</h2>
<a href="agregar_turno_profesor.php" class="boton agregar">➕ Agregar nuevo turno manual</a>

<?php if (empty($datos)): ?>
    <p style="text-align:center; color:white;">No hay asistencias registradas para este mes.</p>
<?php else: ?>
    <?php foreach ($datos as $profesor): ?>
        <table>
            <tr class="subtitulo">
                <td colspan="7"><?= $profesor['nombre'] ?></td>
            </tr>
            <tr>
                <th>Fecha</th>
                <th>Ingreso</th>
                <th>Egreso</th>
                <th>Minutos</th>
                <th>Alumnos</th>
                <th colspan="2">Acciones</th>
            </tr>
            <?php foreach ($profesor['asistencias'] as $a): ?>
            <tr>
                <td><?= $a['fecha'] ?></td>
                <td><?= $a['ingreso'] ?></td>
                <td><?= $a['egreso'] ?></td>
                <td><?= $a['minutos'] ?></td>
                <td><?= $a['alumnos'] ?></td>
                <td>
                    <a href="editar_turno_profesor.php?id=<?= $a['id'] ?>" class="boton editar">✏️ Editar</a>
                </td>
                <td>
                    <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar este turno?');">
                        <input type="hidden" name="eliminar_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="boton eliminar">🗑️ Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="subtitulo">
                <td colspan="4">⏱ Total trabajado</td>
                <td colspan="3">
                    <?php
                        $horas = floor($profesor['total_minutos'] / 60);
                        $min = $profesor['total_minutos'] % 60;
                        echo "{$horas} h {$min} min";
                    ?>
                </td>
            </tr>
        </table>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
