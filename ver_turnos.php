<?php
include 'conexion.php';
include 'menu_cliente.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

$turnos = $conexion->query("
    SELECT 
        turnos.id AS turno_id,
        turnos.dia,
        TIME_FORMAT(turnos.horario_inicio, '%H:%i') AS inicio,
        TIME_FORMAT(turnos.horario_fin, '%H:%i') AS fin,
        profesores.nombre,
        profesores.apellido
    FROM turnos
    LEFT JOIN profesores ON turnos.id_profesor = profesores.id
    WHERE turnos.gimnasio_id = $gimnasio_id
    ORDER BY FIELD(turnos.dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), turnos.horario_inicio
");

$tabla = [];
while ($t = $turnos->fetch_assoc()) {
    $hora = "{$t['inicio']} - {$t['fin']}";
    $dia = $t['dia'];
    $profesor = "{$t['apellido']} {$t['nombre']}";
    $tabla[$hora][$dia] = $profesor;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Turnos Semanales</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>📅 Turnos Semanales</h2>

    <table>
        <thead>
            <tr>
                <th>Horario</th>
                <?php foreach ($dias_semana as $dia): ?>
                    <th><?= $dia ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tabla as $hora => $fila): ?>
                <tr>
                    <td><?= $hora ?></td>
                    <?php foreach ($dias_semana as $dia): ?>
                        <td><?= $fila[$dia] ?? '-' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
