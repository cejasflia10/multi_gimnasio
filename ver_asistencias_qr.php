<?php
session_start();
include 'conexion.php';

// Verificamos que el usuario esté logueado
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// Consulta asistencias de hoy, solo de clientes de este gimnasio
$query = "
SELECT a.fecha, a.hora, c.apellido, c.nombre, d.nombre AS disciplina, 
       m.clases_disponibles, m.fecha_vencimiento
FROM asistencias a
JOIN clientes c ON a.cliente_id = c.id
LEFT JOIN disciplinas d ON c.disciplina_id = d.id
LEFT JOIN membresias m ON m.cliente_id = c.id
WHERE DATE(a.fecha) = CURDATE() AND c.gimnasio_id = ?
ORDER BY a.fecha DESC, a.hora DESC";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias por QR</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
        }
        table {
            width: 100%;
            background: #111;
            color: #f1f1f1;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border: 1px solid #444;
        }
        th {
            background: #222;
        }
    </style>
</head>
<body>
    <h2>Asistencias de Hoy</h2>
    <table>
        <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Disciplina</th>
            <th>Clases Restantes</th>
            <th>Vencimiento</th>
        </tr>
        <?php while ($fila = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?= $fila['fecha'] ?></td>
                <td><?= $fila['hora'] ?></td>
                <td><?= $fila['apellido'] ?></td>
                <td><?= $fila['nombre'] ?></td>
                <td><?= $fila['disciplina'] ?? 'Sin asignar' ?></td>
                <td><?= $fila['clases_disponibles'] ?? '0' ?></td>
                <td><?= $fila['fecha_vencimiento'] ?? 'Sin membresía' ?></td>
            </tr>
        <?php } ?>
    </table>
</body>
</html>
