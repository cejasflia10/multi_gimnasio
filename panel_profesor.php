
<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$fecha_hoy = date('Y-m-d');

$prof = $conexion->query("SELECT apellido, nombre FROM profesores WHERE id = $profesor_id")->fetch_assoc();

// Obtener alumnos de hoy
$alumnos = $conexion->query("
    SELECT c.apellido, c.nombre
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.id_profesor = $profesor_id AND r.fecha = '$fecha_hoy'
    ORDER BY c.apellido
");

// Calcular saldo mensual
$ingresos = $conexion->query("
    SELECT fecha, hora_ingreso, hora_egreso
    FROM asistencias_profesor
    WHERE profesor_id = $profesor_id AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
");

$total_horas = 0;
while ($fila = $ingresos->fetch_assoc()) {
    if ($fila['hora_egreso'] && $fila['hora_ingreso']) {
        $inicio = strtotime($fila['hora_ingreso']);
        $fin = strtotime($fila['hora_egreso']);
        $total_horas += round(($fin - $inicio) / 3600, 2);
    }
}
$valor_hora = 1500;
$saldo = $total_horas * $valor_hora;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1, h2 {
            text-align: center;
        }
        .card {
            background-color: #111;
            padding: 20px;
            margin: 20px auto;
            border: 1px solid gold;
            border-radius: 10px;
            max-width: 800px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
    </style>
</head>
<body>

<h1>👨‍🏫 Bienvenido <?= $prof['apellido'] ?>, <?= $prof['nombre'] ?></h1>

<div class="card">
    <h3>📅 Alumnos del día (<?= $fecha_hoy ?>)</h3>
    <?php if ($alumnos->num_rows > 0): ?>
        <table>
            <thead><tr><th>Apellido</th><th>Nombre</th></tr></thead>
            <tbody>
            <?php while ($a = $alumnos->fetch_assoc()): ?>
                <tr><td><?= $a['apellido'] ?></td><td><?= $a['nombre'] ?></td></tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center;">No hay alumnos registrados hoy.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>💰 Saldo mensual</h3>
    <p style="text-align: center; font-size: 20px;">
        <strong>$<?= number_format($saldo, 2, ',', '.') ?></strong> por <?= $total_horas ?> horas trabajadas
    </p>
</div>

</body>
</html>
