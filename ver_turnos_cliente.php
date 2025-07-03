<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$horas = [];
for ($h = 8; $h < 23; $h++) {
    $hora_inicio = str_pad($h, 2, '0', STR_PAD_LEFT) . ":00:00";
    $hora_fin = str_pad($h + 1, 2, '0', STR_PAD_LEFT) . ":00:00";
    $horas[] = ['inicio' => $hora_inicio, 'fin' => $hora_fin];
}

// Obtener turnos
$turnosQ = $conexion->query("
    SELECT tp.dia, tp.hora_inicio, tp.hora_fin, p.nombre, p.apellido, tp.profesor_id
    FROM turnos_profesor tp
    JOIN profesores p ON tp.profesor_id = p.id
    WHERE tp.gimnasio_id = $gimnasio_id
");

$turnos = [];
while ($t = $turnosQ->fetch_assoc()) {
    $hora_key = $t['hora_inicio']; // ya en formato "08:00:00"
    $turnos[$t['dia']][$hora_key] = $t['apellido'] . ' ' . $t['nombre'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Turnos Semanales</title>
    <style>
        body { background: black; color: gold; font-family: Arial; padding: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid gold; padding: 8px; text-align: center; vertical-align: middle; }
        th { background: #222; }
        td { background: #111; color: white; }
    </style>
</head>
<body>

<h2>📅 Turnos Semanales</h2>

<table>
    <tr>
        <th>Horario</th>
        <?php foreach ($dias as $dia): ?>
            <th><?= $dia ?></th>
        <?php endforeach; ?>
    </tr>
    <?php foreach ($horas as $h): ?>
        <tr>
            <td><?= substr($h['inicio'], 0, 5) ?> - <?= substr($h['fin'], 0, 5) ?></td>
            <?php foreach ($dias as $dia): ?>
                <td>
                    <?= $turnos[$dia][$h['inicio']] ?? '-' ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
