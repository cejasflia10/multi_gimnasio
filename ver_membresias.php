<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "SELECT m.*, c.nombre, c.apellido, c.id AS cliente_id, p.nombre AS nombre_plan 
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
    <title>Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        .boton-volver {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            margin: 10px;
            text-decoration: none;
            display: inline-block;
            border-radius: 6px;
            font-weight: bold;
        }
        .acciones a {
            margin: 0 5px;
            text-decoration: none;
            font-weight: bold;
            padding: 6px 12px;
            border-radius: 4px;
        }
        .editar { background-color: orange; color: black; }
        .eliminar { background-color: crimson; color: white; }
        .renovar { background-color: limegreen; color: black; }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #111;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid gold;
            text-align: center;
        }
        th { background-color: #222; }
        tr.vencida { background-color: #330000; }
        @media screen and (max-width: 600px) {
            table, thead, tbody, th, td, tr { display: block; }
            th { text-align: left; }
            td { text-align: left; border-bottom: 1px solid #444; }
            .acciones a { display: block; margin-bottom: 5px; }
        }
    </style>
</head>
<body>

<h1>Listado de Membresías</h1>
<a href="nueva_membresia.php" class="boton-volver" style="float:right;">➕ Nueva Membresía</a>
<div style="clear:both;"></div>

<table>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Clases</th>
            <th>Total</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $resultado->fetch_assoc()):
            $vencida = (strtotime($fila['fecha_vencimiento']) < strtotime(date("Y-m-d"))) ? 'vencida' : '';
            $id_membresia = $fila['id'];
        ?>
            <tr class="<?= $vencida ?>">
                <td><?= $fila['apellido'] . ', ' . $fila['nombre'] ?></td>
                <td><?= $fila['nombre_plan'] ?></td>
                <td><?= $fila['fecha_inicio'] ?></td>
                <td><?= $fila['fecha_vencimiento'] ?></td>
                <td style="color: <?= $fila['clases_disponibles'] == 0 ? 'red' : 'inherit' ?>">
                    <?= $fila['clases_disponibles'] ?>
                </td>
                <td>$<?= number_format($fila['total'], 2) ?></td>
                <td class="acciones">
                    <a href="editar_membresia.php?id=<?= $id_membresia ?>" class="editar">✏️</a>
                    <a href="eliminar_membresia.php?id=<?= $id_membresia ?>" class="eliminar" onclick="return confirm('¿Eliminar esta membresía?')">❌</a>
                    <a href="renovar_membresia.php?id=<?= $id_membresia ?>" class="renovar">♻️</a>
                    <a href="ver_historial_membresias.php?cliente_id=<?= $fila['cliente_id'] ?>" class="renovar">📜</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<a href="index.php" class="boton-volver">Volver al Menú</a>

</body>
</html>
