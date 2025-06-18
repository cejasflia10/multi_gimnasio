<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["gimnasio_id"])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];

include 'conexion.php';

$sql = "SELECT m.id, CONCAT(c.apellido, ', ', c.nombre) AS cliente, d.nombre AS disciplina,
        p.nombre AS plan, m.fecha_inicio, m.fecha_vencimiento, m.metodo_pago, m.total
        FROM membresias m
        INNER JOIN clientes c ON m.cliente_id = c.id
        INNER JOIN disciplinas d ON m.disciplina_id = d.id
        INNER JOIN planes p ON m.plan_id = p.id
        WHERE m.gimnasio_id = $gimnasio_id
        ORDER BY m.fecha_inicio DESC";

$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membresías</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #FFD700;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #FFD700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #FFD700;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #222;
        }
        tr:nth-child(even) {
            background-color: #1c1c1c;
        }
        .boton-volver {
            background-color: #FFD700;
            border: none;
            color: black;
            padding: 10px 20px;
            text-decoration: none;
            margin: 10px 0;
            display: inline-block;
            font-weight: bold;
            border-radius: 5px;
        }
        .acciones a {
            margin-right: 10px;
            color: #00f;
            text-decoration: underline;
        }
        @media screen and (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th, td {
                text-align: right;
                padding-left: 50%;
                position: relative;
            }
            th::before, td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                text-align: left;
                font-weight: bold;
            }
        }
    </style>
</head>
<body>
    <h1>Listado de Membresías</h1>
    <a href="index.php" class="boton-volver">← Volver al Panel</a>
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Disciplina</th>
                <th>Plan</th>
                <th>Inicio</th>
                <th>Vencimiento</th>
                <th>Método de Pago</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td data-label="Cliente"><?= $fila["cliente"] ?></td>
                    <td data-label="Disciplina"><?= $fila["disciplina"] ?></td>
                    <td data-label="Plan"><?= $fila["plan"] ?></td>
                    <td data-label="Inicio"><?= $fila["fecha_inicio"] ?></td>
                    <td data-label="Vencimiento"><?= $fila["fecha_vencimiento"] ?></td>
                    <td data-label="Método de Pago"><?= $fila["metodo_pago"] ?></td>
                    <td data-label="Total">$<?= number_format($fila["total"], 2, ',', '.') ?></td>
                    <td class="acciones">
                        <a href="editar_membresia.php?id=<?= $fila["id"] ?>">Editar</a>
                        <a href="eliminar_membresia.php?id=<?= $fila["id"] ?>" onclick="return confirm('¿Eliminar esta membresía?')">Eliminar</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>
