<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "
SELECT m.*, 
       c.nombre AS cliente_nombre, c.apellido AS cliente_apellido,
       p.nombre AS plan_nombre,
       a.nombre AS adicional_nombre
FROM membresias m
JOIN clientes c ON m.cliente_id = c.id
JOIN planes p ON m.plan_id = p.id
LEFT JOIN planes_adicionales a ON m.adicional_id = a.id
WHERE m.gimnasio_id = $gimnasio_id
ORDER BY m.fecha_inicio DESC
";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Membresías Registradas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 15px;
        }
        h1 {
            text-align: center;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #444;
            text-align: left;
        }
        th {
            background-color: #222;
        }
        tr.vencida {
            background-color: #400;
            color: white;
        }
        .boton {
            background-color: gold;
            color: black;
            padding: 6px 10px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 5px;
        }
        .volver {
            display: block;
            width: 100%;
            text-align: center;
            margin-top: 25px;
        }

        @media screen and (max-width: 700px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead {
                display: none;
            }
            tr {
                margin-bottom: 20px;
                border: 1px solid #333;
                padding: 10px;
                border-radius: 6px;
            }
            td {
                border: none;
                padding: 6px 10px;
            }
            td::before {
                content: attr(data-label);
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
                color: #ccc;
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
            <th>Adicional</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Clases</th>
            <th>Pago</th>
            <th>Total</th>
            <th>Acción</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $resultado->fetch_assoc()):
            $vencida = (strtotime($fila['fecha_vencimiento']) < strtotime(date('Y-m-d')) || $fila['activa'] == 0);
        ?>
        <tr class="<?= $vencida ? 'vencida' : '' ?>">
            <td data-label="Cliente"><?= $fila['cliente_apellido'] . ', ' . $fila['cliente_nombre'] ?></td>
            <td data-label="Plan"><?= $fila['plan_nombre'] ?></td>
            <td data-label="Adicional"><?= $fila['adicional_nombre'] ?? '-' ?></td>
            <td data-label="Inicio"><?= $fila['fecha_inicio'] ?></td>
            <td data-label="Vencimiento"><?= $fila['fecha_vencimiento'] ?></td>
            <td data-label="Clases"><?= $fila['clases_restantes'] ?></td>
            <td data-label="Pago"><?= ucfirst($fila['metodo_pago']) ?></td>
            <td data-label="Total">$<?= number_format($fila['total'], 2) ?></td>
            <td data-label="Acción">
                <a href="editar_membresia.php?id=<?= $fila['id'] ?>" class="boton">Editar</a><br>
                <a href="eliminar_membresia.php?id=<?= $fila['id'] ?>" class="boton" onclick="return confirm('¿Seguro que desea eliminar esta membresía?')">Eliminar</a><br>
                <a href="renovar_membresia.php?id=<?= $fila['id'] ?>" class="boton">Renovar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<div class="volver">
    <a href="index.php" class="boton">Volver al menú</a>
</div>

</body>
</html>
