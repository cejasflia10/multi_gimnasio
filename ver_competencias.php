<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;

$competencias = $conexion->query("
    SELECT c.fecha, c.nombre_competencia, c.lugar, c.resultado, c.observaciones, cli.apellido, cli.nombre
    FROM competencias c
    JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.profesor_id = $profesor_id
    ORDER BY c.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Competencias del Alumno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h2 { text-align: center; margin-bottom: 20px; }
        table {
            width: 100%; border-collapse: collapse; margin-top: 20px;
            background-color: #111; color: gold;
        }
        th, td {
            border: 1px solid gold; padding: 10px; text-align: center;
        }
        th { background-color: #222; }
        tr:hover { background-color: #222; }
    </style>
</head>
<body>

<h2>🏆 Competencias del Profesor</h2>

<table>
    <thead>
        <tr>
            <th>Alumno</th>
            <th>Fecha</th>
            <th>Competencia</th>
            <th>Lugar</th>
            <th>Resultado</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $competencias->fetch_assoc()): ?>
            <tr>
                <td><?= $fila['apellido'] . ', ' . $fila['nombre'] ?></td>
                <td><?= $fila['fecha'] ?></td>
                <td><?= $fila['nombre_competencia'] ?></td>
                <td><?= $fila['lugar'] ?></td>
                <td><?= $fila['resultado'] ?></td>
                <td><?= $fila['observaciones'] ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
