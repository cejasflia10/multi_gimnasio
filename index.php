<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['gimnasio_id'])) {
    die("Gimnasio no identificado. Iniciá sesión nuevamente.");
}

include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'];
$fecha = date("Y-m-d");

// FUNCIÓN: Obtener asistencias de clientes del día
function obtenerAsistenciasClientes($conexion, $gimnasio_id, $fecha) {
    $sql = "
        SELECT c.apellido, c.nombre, a.fecha_hora, m.fecha_vencimiento
        FROM asistencias_clientes a
        JOIN clientes c ON a.cliente_id = c.id
        JOIN membresias m ON c.id = m.cliente_id
        WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$fecha'
        ORDER BY a.fecha_hora DESC
    ";
    return $conexion->query($sql);
}

$resultado = obtenerAsistenciasClientes($conexion, $gimnasio_id, $fecha);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - Fight Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #fff;
        }
        .contenido {
            margin-left: 220px;
            padding: 20px;
        }
        h2 {
            color: gold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #555;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #333;
            color: gold;
        }
        tr:nth-child(even) {
            background-color: #222;
        }
        @media screen and (max-width: 768px) {
            .contenido {
                margin-left: 0;
                padding: 10px;
            }
            table, th, td {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="contenido">
        <h2>Asistencias de hoy (<?php echo $fecha; ?>)</h2>

        <?php if ($resultado && $resultado->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Apellido</th>
                    <th>Nombre</th>
                    <th>Ingreso</th>
                    <th>Vencimiento</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $fila['apellido']; ?></td>
                    <td><?php echo $fila['nombre']; ?></td>
                    <td><?php echo date("H:i", strtotime($fila['fecha_hora'])); ?></td>
                    <td><?php echo $fila['fecha_vencimiento']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>No se registraron asistencias para hoy.</p>
        <?php endif; ?>
    </div>
</body>
</html>
