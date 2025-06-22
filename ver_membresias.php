<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
if (!$gimnasio_id) {
    die("Acceso denegado.");
}

$sql = "SELECT m.id, c.nombre, c.apellido, m.fecha_inicio, m.fecha_vencimiento, m.total 
        FROM membresias m
        JOIN clientes c ON m.cliente_id = c.id
        WHERE m.gimnasio_id = $gimnasio_id
        ORDER BY m.fecha_vencimiento ASC";

$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #ffd700;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .tabla-contenedor {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #444;
            text-align: left;
        }
        th {
            background-color: #222;
        }
        tr:hover {
            background-color: #333;
        }
        .boton {
            background-color: #ffd700;
            color: #111;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            margin-right: 5px;
        }
        .boton:hover {
            background-color: #e5c100;
        }
        .volver {
            display: block;
            width: fit-content;
            margin: 20px auto;
            background-color: #ffd700;
            color: #111;
            padding: 10px 20px;
            border-radius: 5px;
            text-align: center;
            text-decoration: none;
        }
        @media (max-width: 768px) {
            th, td {
                font-size: 14px;
            }
            .boton {
                font-size: 12px;
                padding: 5px 8px;
            }
        }
    </style>
</head>
<body>

<h2>Membresías Activas</h2>

<div class="tabla-contenedor">
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Fecha Inicio</th>
                <th>Fecha Vencimiento</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['nombre']) ?></td>
                    <td><?= htmlspecialchars($row['apellido']) ?></td>
                    <td><?= htmlspecialchars($row['fecha_inicio']) ?></td>
                    <td><?= htmlspecialchars($row['fecha_vencimiento']) ?></td>
                    <td>$<?= number_format($row['total'], 2, ',', '.') ?></td>
                    <td>
                        <a class="boton" href="editar_membresia.php?id=<?= $row['id'] ?>">Editar</a>
                        <a class="boton" href="eliminar_membresia.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar esta membresía?')">Eliminar</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<a href="index.php" class="volver">Volver al Menú</a>

</body>
</html>
