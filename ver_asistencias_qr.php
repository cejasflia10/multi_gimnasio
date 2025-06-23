<?php
include 'conexion.php';
include 'menu.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "
SELECT c.apellido, c.nombre, c.dni, c.disciplina, m.fecha_vencimiento, a.fecha, a.hora
FROM asistencias a
JOIN clientes c ON a.id_cliente = c.id
LEFT JOIN membresias m ON c.id = m.id_cliente
WHERE a.fecha = CURDATE() AND c.gimnasio_id = $gimnasio_id
ORDER BY a.hora DESC
";
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias por QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
            color: white;
        }

        th, td {
            padding: 10px;
            border: 1px solid #555;
            text-align: center;
        }

        th {
            background-color: #333;
            color: gold;
        }

        tr:hover {
            background-color: #444;
        }

        .volver {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: gold;
            color: black;
            text-decoration: none;
            border-radius: 5px;
        }

        .volver:hover {
            background-color: #e5b800;
        }
    </style>
</head>
<body>

    <h2>Asistencias del Día (QR)</h2>

    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Disciplina</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Vencimiento</th>
        </tr>

        <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= $fila['apellido'] ?></td>
                <td><?= $fila['nombre'] ?></td>
                <td><?= $fila['dni'] ?></td>
                <td><?= $fila['disciplina'] ?></td>
                <td><?= $fila['fecha'] ?></td>
                <td><?= $fila['hora'] ?></td>
                <td><?= $fila['fecha_vencimiento'] ?: 'Sin datos' ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <a href="index.php" class="volver">Volver al panel</a>

</body>
</html>
