<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];

$query = "
SELECT a.fecha, a.hora, c.apellido, c.nombre, d.nombre AS disciplina,
       m.clases_disponibles, m.fecha_vencimiento
FROM asistencias a
JOIN clientes c ON a.cliente_id = c.id
LEFT JOIN disciplinas d ON c.disciplina_id = d.id
LEFT JOIN membresias m ON m.id = (
    SELECT id FROM membresias
    WHERE cliente_id = c.id
    AND fecha_vencimiento >= CURDATE()
    ORDER BY fecha_vencimiento DESC
    LIMIT 1
)
WHERE DATE(a.fecha) = CURDATE()
  AND c.gimnasio_id = ?
ORDER BY a.hora DESC";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias de Hoy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 { color: yellow; text-align: center; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #444;
            padding: 10px;
            text-align: center;
        }
        th { background-color: #222; color: white; }
        tr:nth-child(even) { background-color: #111; }

        @media screen and (max-width: 600px) {
            table, thead, tbody, th, td, tr { display: block; }
            th { display: none; }
            td {
                position: relative;
                padding-left: 50%;
                text-align: left;
            }
            td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                color: #888;
                font-weight: bold;
            }
        }
    </style>
</head>
<body>

<h1>Asistencias de Hoy</h1>

<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Disciplina</th>
            <th>Clases Restantes</th>
            <th>Vencimiento</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
        <tr>
            <td data-label="Fecha"><?= $fila['fecha'] ?></td>
            <td data-label="Hora"><?= $fila['hora'] ?></td>
            <td data-label="Apellido"><?= $fila['apellido'] ?></td>
            <td data-label="Nombre"><?= $fila['nombre'] ?></td>
            <td data-label="Disciplina"><?= $fila['disciplina'] ?></td>
            <td data-label="Clases Restantes"><?= $fila['clases_disponibles'] ?? '0' ?></td>
            <td data-label="Vencimiento"><?= $fila['fecha_vencimiento'] ?? '-' ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
