<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$es_admin = $_SESSION['rol'] === 'admin';
$hoy = date('Y-m-d');

// Condición por gimnasio
$condicion = $es_admin ? "1" : "a.id_gimnasio = $gimnasio_id";

// Clientes
$sql_clientes = "
SELECT c.nombre, c.apellido, c.dni, c.disciplina, m.fecha_vencimiento, m.clases_restantes, a.fecha, a.hora
FROM asistencias a
JOIN clientes c ON a.cliente_id = c.id
LEFT JOIN membresias m ON m.cliente_id = c.id AND m.fecha_vencimiento >= CURDATE()
WHERE $condicion AND a.fecha = CURDATE()
ORDER BY a.hora DESC";
$res_clientes = $conexion->query($sql_clientes);

// Profesores
$sql_profesores = "
SELECT p.apellido, a.fecha, a.hora, a.tipo
FROM asistencias a
JOIN profesores p ON a.profesor_id = p.id
WHERE $condicion AND a.fecha = CURDATE()
ORDER BY a.hora DESC";
$res_profesores = $conexion->query($sql_profesores);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias del Día - QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 1rem;
        }
        h2 {
            color: gold;
            border-bottom: 2px solid gold;
            padding-bottom: 5px;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #222;
        }
        tr:nth-child(even) {
            background-color: #1a1a1a;
        }
        tr:hover {
            background-color: #333;
        }
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                display: none;
            }
            td {
                position: relative;
                padding-left: 50%;
                border: none;
                border-bottom: 1px solid gold;
            }
            td::before {
                position: absolute;
                top: 10px;
                left: 10px;
                font-weight: bold;
                white-space: nowrap;
            }
            td:nth-of-type(1)::before { content: "Nombre"; }
            td:nth-of-type(2)::before { content: "DNI"; }
            td:nth-of-type(3)::before { content: "Disciplina"; }
            td:nth-of-type(4)::before { content: "Hora"; }
            td:nth-of-type(5)::before { content: "Clases"; }
            td:nth-of-type(6)::before { content: "Vencimiento"; }
        }
    </style>
</head>
<body>

    <h2>🧍‍♂️ Asistencias de Clientes (<?= $hoy ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Disciplina</th>
                <th>Hora</th>
                <th>Clases</th>
                <th>Vencimiento</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $res_clientes->fetch_assoc()) { ?>
                <tr>
                    <td><?= $fila['apellido'] . ', ' . $fila['nombre'] ?></td>
                    <td><?= $fila['dni'] ?></td>
                    <td><?= $fila['disciplina'] ?></td>
                    <td><?= $fila['hora'] ?></td>
                    <td><?= $fila['clases_restantes'] ?? '0' ?></td>
                    <td><?= $fila['fecha_vencimiento'] ?? '---' ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <h2>🎓 Asistencias de Profesores (<?= $hoy ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Hora</th>
                <th>Tipo</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $res_profesores->fetch_assoc()) { ?>
                <tr>
                    <td><?= $fila['apellido'] ?></td>
                    <td><?= $fila['hora'] ?></td>
                    <td><?= $fila['tipo'] === 'ingreso' ? 'Ingreso' : 'Egreso' ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

</body>
</html>
