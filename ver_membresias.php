<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "SELECT m.*, c.nombre, c.apellido, p.nombre AS nombre_plan 
          FROM membresias m 
          JOIN clientes c ON m.cliente_id = c.id 
          JOIN planes p ON m.plan_id = p.id 
          WHERE m.gimnasio_id = $gimnasio_id 
          ORDER BY m.fecha_inicio DESC";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 25px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #111;
        }
        th, td {
            padding: 10px;
            border: 1px solid gold;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        tr.vencida {
            background-color: #330000;
        }
        .boton-volver {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            margin-top: 20px;
            text-decoration: none;
            display: inline-block;
            border-radius: 6px;
            font-weight: bold;
        }
        @media screen and (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th {
                text-align: left;
            }
            td {
                text-align: left;
                border: none;
                border-bottom: 1px solid #555;
            }
        }
    </style>
</head>
<body>

<h1>Membresías Registradas</h1>

<table>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Clases</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $resultado->fetch_assoc()):
            $vencida = (strtotime($fila['fecha_vencimiento']) < strtotime(date("Y-m-d"))) ? 'vencida' : '';
        ?>
            <tr class="<?= $vencida ?>">
                <td><?= $fila['apellido'] . ', ' . $fila['nombre'] ?></td>
                <td><?= $fila['nombre_plan'] ?></td>
                <td><?= $fila['fecha_inicio'] ?></td>
                <td><?= $fila['fecha_vencimiento'] ?></td>
                <td><?= $fila['clases_disponibles'] ?></td>
                <td>$<?= number_format($fila['total'], 2) ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<br>
<a href="index.php" class="boton-volver">Volver al menú</a>

</body>
</html>
