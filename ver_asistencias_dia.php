<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date('Y-m-d');

// CLIENTES
$clientes_q = $conexion->query("SELECT c.apellido, c.nombre, a.hora FROM asistencias a JOIN clientes c ON a.cliente_id = c.id WHERE a.fecha = '$hoy' AND a.gimnasio_id = $gimnasio_id");

// PROFESORES
$profesores_q = $conexion->query("SELECT p.apellido, p.nombre, a.hora_entrada, a.hora_salida FROM asistencias_profesor a JOIN profesores p ON a.profesor_id = p.id WHERE a.fecha = '$hoy' AND a.gimnasio_id = $gimnasio_id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias del Día</title>
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; text-align: center; }
        table { margin: 20px auto; border-collapse: collapse; width: 90%; }
        th, td { border: 1px solid gold; padding: 8px; }
        th { background-color: #111; }
    </style>
</head>
<body>
    <h1>Asistencias del Día - <?php echo date("d/m/Y"); ?></h1>

    <h2>Clientes</h2>
    <table>
        <tr><th>Apellido</th><th>Nombre</th><th>Hora Ingreso</th></tr>
        <?php if ($clientes_q->num_rows > 0): while ($c = $clientes_q->fetch_assoc()): ?>
            <tr><td><?= $c['apellido'] ?></td><td><?= $c['nombre'] ?></td><td><?= $c['hora'] ?></td></tr>
        <?php endwhile; else: ?>
            <tr><td colspan="3">Sin registros</td></tr>
        <?php endif; ?>
    </table>

    <h2>Profesores</h2>
    <table>
        <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Egreso</th></tr>
        <?php if ($profesores_q->num_rows > 0): while ($p = $profesores_q->fetch_assoc()): ?>
            <tr>
                <td><?= $p['apellido'] ?></td>
                <td><?= $p['nombre'] ?></td>
                <td><?= $p['hora_entrada'] ?></td>
                <td><?= $p['hora_salida'] ?? '—' ?></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="4">Sin registros</td></tr>
        <?php endif; ?>
    </table>
</body>
</html>
