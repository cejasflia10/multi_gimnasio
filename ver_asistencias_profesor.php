<?php
include 'conexion.php';
session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');
$mes_actual = date('m');
$anio_actual = date('Y');

// Obtener listado de profesores
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores ORDER BY apellido");

$profesor_id = $_GET['id'] ?? null;
$datos = [];

if ($profesor_id) {
    $query = $conexion->query("
        SELECT fecha, hora_ingreso, hora_egreso, alumnos_presentes, monto_a_pagar
        FROM asistencias_profesor 
        WHERE profesor_id = $profesor_id 
        AND MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual
        ORDER BY fecha DESC
    ");

    while ($fila = $query->fetch_assoc()) {
        $datos[] = $fila;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Asistencias Profesor</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        select, button {
            padding: 8px;
            font-size: 16px;
        }
        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            color: white;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #111;
        }
    </style>
</head>
<body>

<h2>Ver Asistencias de Profesor</h2>

<form method="GET">
    <label>Seleccionar Profesor: </label>
    <select name="id" onchange="this.form.submit()">
        <option value="">-- Elegir --</option>
        <?php while ($p = $profesores->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" <?= $profesor_id == $p['id'] ? 'selected' : '' ?>>
                <?= $p['apellido'] . ', ' . $p['nombre'] ?>
            </option>
        <?php endwhile; ?>
    </select>
</form>

<?php if ($profesor_id && count($datos)): ?>
    <h3>Asistencias en <?= date('F Y') ?></h3>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora Ingreso</th>
                <th>Hora Egreso</th>
                <th>Alumnos</th>
                <th>Monto a Pagar</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total = 0;
            foreach ($datos as $d): 
                $total += $d['monto_a_pagar'];
            ?>
                <tr>
                    <td><?= $d['fecha'] ?></td>
                    <td><?= $d['hora_ingreso'] ?></td>
                    <td><?= $d['hora_egreso'] ?></td>
                    <td><?= $d['alumnos_presentes'] ?></td>
                    <td>$<?= number_format($d['monto_a_pagar'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th colspan="4">TOTAL A PAGAR</th>
                <th>$<?= number_format($total, 2) ?></th>
            </tr>
        </tbody>
    </table>
<?php elseif ($profesor_id): ?>
    <p>No hay asistencias registradas para este mes.</p>
<?php endif; ?>

</body>
</html>
