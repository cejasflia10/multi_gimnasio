<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($gimnasio_id == 0) {
    echo "Acceso denegado.";
    exit;
}

$fecha_hoy = date('Y-m-d');

// Consultar asistencias de clientes
$clientes_q = $conexion->query("
    SELECT c.apellido, c.nombre, ac.hora
    FROM asistencias_clientes ac
    JOIN clientes c ON ac.cliente_id = c.id
    WHERE ac.fecha = '$fecha_hoy' AND ac.gimnasio_id = $gimnasio_id
    ORDER BY ac.hora ASC
");

// Consultar asistencias de profesores
$profesores_q = $conexion->query("
    SELECT p.apellido, p.nombre, ap.hora_ingreso, ap.hora_egreso
    FROM asistencias_profesor ap
    JOIN profesores p ON ap.profesor_id = p.id
    WHERE ap.fecha = '$fecha_hoy' AND ap.gimnasio_id = $gimnasio_id
    ORDER BY ap.hora_ingreso ASC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias del Día</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background: #000; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid gold; padding: 8px; text-align: center; }
        th { background: #222; }
    </style>
</head>
<body>
    <h2>📋 Asistencias del Día - <?= date('d/m/Y') ?></h2>

    <h3>Clientes</h3>
    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Hora Ingreso</th>
        </tr>
        <?php if ($clientes_q->num_rows > 0): ?>
            <?php while ($c = $clientes_q->fetch_assoc()): ?>
                <tr>
                    <td><?= $c['apellido'] ?></td>
                    <td><?= $c['nombre'] ?></td>
                    <td><?= $c['hora'] ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3">Sin registros</td></tr>
        <?php endif; ?>
    </table>

    <h3>Profesores</h3>
    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Ingreso</th>
            <th>Egreso</th>
        </tr>
        <?php if ($profesores_q->num_rows > 0): ?>
            <?php while ($p = $profesores_q->fetch_assoc()): ?>
                <tr>
                    <td><?= $p['apellido'] ?></td>
                    <td><?= $p['nombre'] ?></td>
                    <td><?= $p['hora_ingreso'] ?></td>
                    <td><?= $p['hora_egreso'] ?? '—' ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">Sin registros</td></tr>
        <?php endif; ?>
    </table>
</body>
</html>
