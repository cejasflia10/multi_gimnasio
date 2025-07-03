<?php
include 'conexion.php';
include 'menu_cliente.php';


$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

$turnos = $conexion->query("
    SELECT 
        turnos.id AS turno_id,
        turnos.dia,
        TIME_FORMAT(turnos.horario_inicio, '%H:%i') AS horario_inicio,
        TIME_FORMAT(turnos.horario_fin, '%H:%i') AS horario_fin,
        profesores.nombre,
        profesores.apellido
    FROM turnos
    LEFT JOIN profesores ON turnos.id_profesor = profesores.id
    WHERE turnos.gimnasio_id = $gimnasio_id
    ORDER BY FIELD(turnos.dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), turnos.horario_inicio
");


$tabla = [];
while ($t = $turnos->fetch_assoc()) {
    $hora = $t['horario']; // por ejemplo: "08:00 - 09:00"
    $dia = $t['dia'];
    $tabla[$hora][$dia] = $t['nombre_profesor'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Turnos Semanales</title>
    <style>
        body {
            background: black;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        td {
            background-color: #111;
            color: white;
        }
    </style>
</head>
<body>

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

</body>
</html>
