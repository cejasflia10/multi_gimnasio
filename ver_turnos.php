<?php
include 'conexion.php';
include 'menu_cliente.php';

// Obtener todos los turnos del gimnasio logueado
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// Obtener todos los turnos
$turnos = $conexion->query("
    SELECT * FROM turnos
    WHERE gimnasio_id = $gimnasio_id
    ORDER BY FIELD(dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), hora_inicio
");

// Organizar turnos por hora y día
$tabla = [];
while ($t = $turnos->fetch_assoc()) {
    $hora = $t['hora_inicio'] . ' - ' . $t['hora_fin'];
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
