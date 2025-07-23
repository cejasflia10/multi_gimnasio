<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Filtros
$alumno_id = $_GET['alumno_id'] ?? '';
$filtro = $_GET['filtro'] ?? 'mensual';

// Fechas según filtro
$hoy = date('Y-m-d');
switch ($filtro) {
    case 'semanal':
        $fecha_inicio = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'anual':
        $fecha_inicio = date('Y-01-01');
        break;
    case 'mensual':
    default:
        $fecha_inicio = date('Y-m-01');
        break;
}

// Obtener alumnos del profesor por turnos
$alumnos_q = $conexion->query("
    SELECT DISTINCT c.id, CONCAT(c.apellido, ' ', c.nombre) AS nombre
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.profesor_id = $profesor_id
      AND a.gimnasio_id = $gimnasio_id
");

$alumnos = [];
while ($row = $alumnos_q->fetch_assoc()) {
    $alumnos[] = $row;
}

// Consultar progresos si se seleccionó alumno
$progresos = [];
if ($alumno_id) {
    $res = $conexion->query("
        SELECT fecha, peso_antes, peso_despues, esfuerzo, duracion_entrenamiento, calorias_estimadas, enfermedades
        FROM progreso_cliente
        WHERE cliente_id = $alumno_id
          AND gimnasio_id = $gimnasio_id
          AND fecha BETWEEN '$fecha_inicio' AND '$hoy'
        ORDER BY fecha DESC
    ");
    while ($row = $res->fetch_assoc()) {
        $progresos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Progreso de Alumnos</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial;
            padding: 20px;
        }
        select, button {
            padding: 6px 10px;
            margin: 5px;
            border-radius: 5px;
        }
        table {
            width: 100%;
            background: #111;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: center;
        }
        th {
            background: #222;
        }
        .filtros {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
    </style>
</head>
<body>

<h2>📈 Progreso de Alumnos</h2>

<form method="GET" class="filtros">
    <label>Alumno:
        <select name="alumno_id" required>
            <option value="">Seleccionar</option>
            <?php foreach ($alumnos as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $a['id'] == $alumno_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Período:
        <select name="filtro">
            <option value="semanal" <?= $filtro == 'semanal' ? 'selected' : '' ?>>Semanal</option>
            <option value="mensual" <?= $filtro == 'mensual' ? 'selected' : '' ?>>Mensual</option>
            <option value="anual" <?= $filtro == 'anual' ? 'selected' : '' ?>>Anual</option>
        </select>
    </label>
    <button type="submit">Filtrar</button>
</form>

<?php if ($alumno_id && empty($progresos)): ?>
    <p style="color:red;">No hay datos registrados para este período.</p>
<?php endif; ?>

<?php if (!empty($progresos)): ?>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Peso Antes (kg)</th>
                <th>Peso Después (kg)</th>
                <th>Esfuerzo</th>
                <th>Duración (min)</th>
                <th>Calorías Estimadas</th>
                <th>Enfermedades</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($progresos as $p): ?>
                <tr>
                    <td><?= $p['fecha'] ?></td>
                    <td><?= $p['peso_antes'] ?></td>
                    <td><?= $p['peso_despues'] ?></td>
                    <td><?= $p['esfuerzo'] ?></td>
                    <td><?= $p['duracion_entrenamiento'] ?></td>
                    <td><?= $p['calorias_estimadas'] ?></td>
                    <td><?= $p['enfermedades'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
