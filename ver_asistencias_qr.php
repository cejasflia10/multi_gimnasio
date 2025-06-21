<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado");
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// Buscar membresía más reciente por cliente (asumiendo la más reciente como activa)
$sql = "SELECT a.fecha, a.hora, c.apellido, c.nombre, d.nombre AS disciplina, 
               m.fecha_vencimiento, m.clases_restantes
        FROM asistencias a
        JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN disciplinas d ON c.disciplina_id = d.id
        LEFT JOIN (
            SELECT cliente_id, MAX(id) as max_id
            FROM membresias
            GROUP BY cliente_id
        ) ultima ON ultima.cliente_id = c.id
        LEFT JOIN membresias m ON m.id = ultima.max_id
        WHERE c.gimnasio_id = $gimnasio_id
        ORDER BY a.fecha DESC, a.hora DESC
        LIMIT 100";

$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias de Clientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: gold;
            margin-bottom: 20px;
        }

        .btn-volver {
            background-color: gold;
            color: #000;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 20px;
        }

        .tabla {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        .tabla th, .tabla td {
            border: 1px solid #444;
            padding: 10px;
            text-align: center;
        }

        .tabla th {
            background-color: #222;
            color: gold;
        }

        @media screen and (max-width: 600px) {
            .tabla th, .tabla td {
                font-size: 14px;
                padding: 8px;
            }

            .btn-volver {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<h1>Asistencias de Clientes</h1>
<a href="index.php" class="btn-volver">⬅ Volver al Menú</a>

<table class="tabla">
    <tr>
        <th>Nombre</th>
        <th>Disciplina</th>
        <th>Clases Restantes</th>
        <th>Fecha Vencimiento</th>
        <th>Fecha</th>
        <th>Hora</th>
    </tr>
    <?php while ($row = $resultado->fetch_assoc()) { ?>
        <tr>
            <td><?php echo $row['apellido'] . ', ' . $row['nombre']; ?></td>
            <td><?php echo $row['disciplina'] ?? 'No definida'; ?></td>
            <td><?php echo $row['clases_restantes'] ?? '-'; ?></td>
            <td><?php echo $row['fecha_vencimiento'] ?? '-'; ?></td>
            <td><?php echo $row['fecha']; ?></td>
            <td><?php echo $row['hora']; ?></td>
        </tr>
    <?php } ?>
</table>

</body>
</html>
