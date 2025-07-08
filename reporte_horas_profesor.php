<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$profesor_id = isset($_GET['profesor_id']) ? intval($_GET['profesor_id']) : 0;

// Obtener listado de profesores del gimnasio
$profesores_q = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id");
$profesores = [];
while ($row = $profesores_q->fetch_assoc()) {
    $profesores[] = $row;
}
if ($profesor_id == 0 && count($profesores) > 0) {
    $profesor_id = $profesores[0]['id'];
}

// Obtener nombre del profesor actual
$nombre_profesor = '';
foreach ($profesores as $p) {
    if ($p['id'] == $profesor_id) {
        $nombre_profesor = $p['apellido'] . ' ' . $p['nombre'];
        break;
    }
}

// Turnos
$turnos_q = $conexion->query("SELECT * FROM asistencias_profesores WHERE profesor_id = $profesor_id AND gimnasio_id = $gimnasio_id ORDER BY fecha DESC, hora_ingreso DESC");

$valor_hora = 500; // Fijo o podés traerlo de tarifas
$total_pago = 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Horas</title>
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 8px;
            text-align: center;
        }
        th {
            background: #111;
        }
        td {
            border-bottom: 1px solid #333;
        }
        .verde { color: lime; }
        a { color: lime; text-decoration: none; }
        select {
            background: #222;
            color: white;
            padding: 5px;
            border: 1px solid #555;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<h2 style="text-align:center;">🕒 Reporte de Horas - <?= $nombre_profesor ?></h2>

<!-- Selector de profesor -->
<form method="get" style="text-align:center;">
    <label>Seleccionar Profesor:</label>
    <select name="profesor_id" onchange="this.form.submit()">
        <?php foreach ($profesores as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $p['id'] == $profesor_id ? 'selected' : '' ?>>
                <?= $p['apellido'] . ' ' . $p['nombre'] ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<table>
    <tr>
        <th>Fecha</th>
        <th>Ingreso</th>
        <th>Salida</th>
        <th>Alumnos</th>
        <th>Horas</th>
        <th>Pago</th>
        <th>Nota</th>
        <th>Editar</th>
    </tr>

<?php while ($t = $turnos_q->fetch_assoc()): 
    $horas = 0;
    $pago = 0;
    $nota = '';

    if (!empty($t['hora_ingreso']) && !empty($t['hora_salida'])) {
        $ingreso = new DateTime($t['hora_ingreso']);
        $salida = new DateTime($t['hora_salida']);
        $horas = ($salida->getTimestamp() - $ingreso->getTimestamp()) / 3600;
        $pago = $horas * $valor_hora;
        $total_pago += $pago;

        $alumnos = $t['alumnos_manual'] ?? 0;

        if ($alumnos <= 3 && $alumnos > 0) {
            $nota = "Hasta 3 alumnos - mínimo";
        } elseif ($alumnos > 3 && $alumnos < 7) {
            $nota = "4 a 6 alumnos - 50% extra";
        } elseif ($alumnos >= 7) {
            $nota = "7 o más - 100% extra";
        }
    } else {
        $alumnos = 0;
        $nota = "Turno incompleto (falta salida)";
    }
?>
<tr>
    <td><?= $t['fecha'] ?></td>
    <td><?= $t['hora_ingreso'] ?></td>
    <td><?= $t['hora_salida'] ?: '-' ?></td>
    <td><?= $alumnos ?></td>
    <td><?= number_format($horas, 2) ?></td>
    <td>$<?= number_format($pago, 0) ?></td>
    <td><?= $nota ?></td>
    <td><a href="editar_turno_profesor.php?id=<?= $t['id'] ?>">✏️</a></td>
</tr>
<?php endwhile; ?>

    <tr>
        <td colspan="5" style="text-align:right;"><strong>TOTAL</strong></td>
        <td colspan="3" class="verde">$<?= number_format($total_pago, 0) ?></td>
    </tr>
</table>

<div style="text-align:center; margin-top:20px;">
    <a href="agregar_turno_profesor.php" style="color:lime; font-size:18px;">➕ Agregar Turno Manual</a>
</div>

</body>
</html>
