<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$filtro_fecha = $_GET['fecha'] ?? date('Y-m-d');

// Obtener profesores con asistencias en la fecha
$sql = "
    SELECT a.id, a.fecha, a.hora_ingreso, a.hora_egreso,
           a.profesor_id,
           p.apellido, p.nombre,
           TIMESTAMPDIFF(MINUTE, a.hora_ingreso, a.hora_egreso) AS minutos
    FROM asistencias_profesores a
    INNER JOIN profesores p ON a.profesor_id = p.id
    WHERE a.fecha = '$filtro_fecha' AND a.gimnasio_id = $gimnasio_id
    ORDER BY a.fecha DESC, a.hora_ingreso DESC
";
$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🕓 Asistencias Profesores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
        }
        .contenedor {
            max-width: 1000px;
            margin: 30px auto;
            background-color: #111;
            padding: 25px;
            border-radius: 10px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #000;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        form {
            text-align: center;
            margin-bottom: 20px;
        }
        input[type="date"] {
            padding: 8px;
            font-size: 16px;
        }
        button {
            padding: 8px 16px;
            font-size: 16px;
            background-color: gold;
            border: none;
            cursor: pointer;
            margin-left: 10px;
        }
        .alumnos-turno {
            background-color: #111;
            border: 1px dashed gold;
            margin-bottom: 25px;
            padding: 10px;
            font-size: 15px;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🕓 Asistencias de Profesores</h2>

    <form method="get">
        <label>📅 Seleccionar fecha:</label>
        <input type="date" name="fecha" value="<?= $filtro_fecha ?>">
        <button type="submit">Filtrar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Profesor</th>
                <th>Fecha</th>
                <th>Hora Ingreso</th>
                <th>Hora Egreso</th>
                <th>Tiempo Trabajado</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= $fila['apellido'] . ' ' . $fila['nombre'] ?></td>
                        <td><?= $fila['fecha'] ?></td>
                        <td><?= $fila['hora_ingreso'] ?? '-' ?></td>
                        <td><?= $fila['hora_egreso'] ?? '-' ?></td>
                        <td>
                            <?php
                            if ($fila['hora_egreso']) {
                                $horas = floor($fila['minutos'] / 60);
                                $min = $fila['minutos'] % 60;
                                echo "{$horas}h {$min}m";
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>
                    </tr>

                    <!-- Buscar alumnos que ingresaron durante el turno del profesor -->
                    <?php
                    $hora_ini = $fila['hora_ingreso'];
                    $hora_fin = $fila['hora_egreso'];
                    $fecha = $fila['fecha'];

                    $alumnos = $conexion->query("
                        SELECT c.apellido, c.nombre, c.dni, a.hora
                        FROM asistencias a
                        INNER JOIN clientes c ON c.id = a.cliente_id
                        WHERE a.gimnasio_id = $gimnasio_id
                          AND a.fecha = '$fecha'
                          AND a.hora BETWEEN '$hora_ini' AND '$hora_fin'
                        ORDER BY a.hora ASC
                    ");
                    ?>

                    <tr>
                        <td colspan="5" class="alumnos-turno">
                            <strong>👥 Alumnos presentes durante el turno:</strong><br>
                            <?php if ($alumnos && $alumnos->num_rows > 0): ?>
                                <ul style="list-style: none; padding-left: 0;">
                                    <?php while ($alumno = $alumnos->fetch_assoc()): ?>
                                        <li>🕒 <?= $alumno['hora'] ?> - <?= $alumno['apellido'] ?> <?= $alumno['nombre'] ?> (DNI: <?= $alumno['dni'] ?>)</li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <span>Sin alumnos registrados en este turno.</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">Sin registros para la fecha seleccionada.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
