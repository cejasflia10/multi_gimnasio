<?php
include 'conexion.php';
include 'menu.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$gimnasio_id = $_SESSION['gimnasio_id'];

// Consulta con todos los JOIN necesarios
$query = "SELECT turnos.id, dias.nombre AS dia, horarios.hora_inicio, horarios.hora_fin, 
                 profesores.nombre AS profesor, profesores.apellido, turnos.cupo_maximo
          FROM turnos
          LEFT JOIN dias ON turnos.id_dia = dias.id
          LEFT JOIN horarios ON turnos.id_horario = horarios.id
          LEFT JOIN profesores ON turnos.id_profesor = profesores.id
          WHERE turnos.gimnasio_id = $gimnasio_id
          ORDER BY dias.id, horarios.hora_inicio";

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
            font-family: Arial;
            margin-left: 260px;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            background-color: #222;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #444;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #333;
        }
        .boton {
            padding: 6px 10px;
            background-color: gold;
            color: black;
            text-decoration: none;
            border-radius: 5px;
            margin: 2px;
        }
    </style>
</head>
<body>
    <h1>Turnos y Horarios</h1>
    <a class="boton" href="agregar_turno.php">+ Nuevo Turno</a>

    <table>
        <thead>
            <tr>
                <th>Día</th>
                <th>Horario</th>
                <th>Profesor</th>
                <th>Cupo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td><?= $fila['dia'] ?></td>
                    <td><?= substr($fila['hora_inicio'], 0, 5) ?> - <?= substr($fila['hora_fin'], 0, 5) ?></td>
                    <td><?= $fila['apellido'] . ' ' . $fila['profesor'] ?></td>
                    <td><?= $fila['cupo_maximo'] ?></td>
                    <td>
                        <a class="boton" href="editar_turno.php?id=<?= $fila['id'] ?>">Editar</a>
                        <a class="boton" href="eliminar_turno.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Eliminar este turno?')">Eliminar</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>
