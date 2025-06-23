<?php
include 'conexion.php';
include 'menu.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$gimnasio_id = $_SESSION['gimnasio_id'];

$query = "SELECT turnos.*, profesores.nombre AS profesor
          FROM turnos 
          LEFT JOIN profesores ON turnos.profesor_id = profesores.id
          WHERE turnos.gimnasio_id = $gimnasio_id
          ORDER BY FIELD(dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), horario_inicio";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Turnos y Horarios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin-left: 260px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            border: 1px solid #444;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #333;
        }
        h1 {
            color: gold;
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Turnos y Horarios</h1>
    <table>
        <thead>
            <tr>
                <th>Día</th>
                <th>Horario</th>
                <th>Profesor</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td><?= $fila['dia'] ?></td>
                    <td><?= substr($fila['horario_inicio'], 0, 5) ?> - <?= substr($fila['horario_fin'], 0, 5) ?></td>
                    <td><?= $fila['profesor'] ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>
