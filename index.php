<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'];
$fecha_actual = date('Y-m-d');

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $fecha_actual = date('Y-m-d');
    $sql = "
        SELECT c.apellido, c.nombre, a.fecha_hora
        FROM asistencias_clientes a
        INNER JOIN clientes c ON a.cliente_id = c.id
        WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$fecha_actual'
        ORDER BY a.fecha_hora DESC
    ";
    return $conexion->query($sql);
}

$resultado = obtenerAsistenciasClientes($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Principal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #fff;
        }
        .container {
            margin-left: 250px;
            padding: 20px;
        }
        .tarjeta {
            background-color: #1a1a1a;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 0 5px #f1c40f;
        }
        .tarjeta h3 {
            margin-top: 0;
            color: #f1c40f;
        }
        .tabla-asistencias {
            width: 100%;
            border-collapse: collapse;
        }
        .tabla-asistencias th, .tabla-asistencias td {
            border: 1px solid #333;
            padding: 10px;
            text-align: left;
        }
        .tabla-asistencias th {
            background-color: #222;
        }
        @media screen and (max-width: 768px) {
            .container {
                margin-left: 0;
                padding: 10px;
            }
            .tarjeta {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="tarjeta">
        <h3>👥 Asistencias de Clientes (<?php echo date('d/m/Y'); ?>)</h3>
        <?php if ($resultado && $resultado->num_rows > 0): ?>
        <table class="tabla-asistencias">
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Hora de ingreso</th>
            </tr>
            <?php while ($row = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['apellido']; ?></td>
                    <td><?php echo $row['nombre']; ?></td>
                    <td><?php echo date('H:i', strtotime($row['fecha_hora'])); ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
            <p>No hay asistencias registradas hoy.</p>
        <?php endif; ?>
    </div>

    <!-- Aquí podés seguir agregando tarjetas con: ventas, pagos, vencimientos, cumpleaños -->

</div>
</body>
</html>
