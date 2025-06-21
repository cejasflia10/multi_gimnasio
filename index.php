<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$nombre_gimnasio = $_SESSION['nombre_gimnasio'] ?? 'Academy';

// Obtener asistencias de clientes
function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $sql = "SELECT c.apellido, c.nombre, a.fecha_ingreso, a.hora 
            FROM asistencias_clientes a
            JOIN clientes c ON a.cliente_id = c.id
            WHERE a.fecha_ingreso = '$hoy' AND c.gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

$asistencias_clientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$hoy = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h1, h2 {
            text-align: center;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #111;
        }
        @media (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

    <h1>🏋️ Fight Academy - <?= strtoupper($nombre_gimnasio) ?></h1>
    <h2>📊 Panel de Control</h2>

    <h3>👥 Asistencias de Clientes - <?= $hoy ?></h3>
    <table>
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Fecha</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($asistencias_clientes && $asistencias_clientes->num_rows > 0): ?>
            <?php while($row = $asistencias_clientes->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['fecha_ingreso'] ?></td>
                <td><?= $row['hora'] ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">Sin registros</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
