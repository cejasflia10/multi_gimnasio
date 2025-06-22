<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

if (!isset($_SESSION['gimnasio_id'])) {
    $_SESSION['gimnasio_id'] = 1; // Modo prueba
}
$gimnasio_id = $_SESSION['gimnasio_id'];
$hoy = date('Y-m-d');

// Consulta de asistencias del día
$query = "
SELECT c.apellido, c.nombre, a.fecha, a.hora
FROM asistencias a
INNER JOIN clientes c ON c.id = a.cliente_id
WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$hoy'
ORDER BY a.hora DESC
";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias del Día</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 10px;
        }

        .info {
            text-align: center;
            font-size: 16px;
            margin-bottom: 15px;
            color: lime;
        }

        .no-result {
            text-align: center;
            color: yellow;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1c1c1c;
        }

        th, td {
            padding: 10px;
            border: 1px solid #f1c40f;
            text-align: center;
        }

        th {
            background-color: #222;
        }

        @media (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }

            th {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            td {
                position: relative;
                padding-left: 50%;
                border: none;
                border-bottom: 1px solid #f1c40f;
            }

            td::before {
                position: absolute;
                top: 10px;
                left: 10px;
                width: 45%;
                white-space: nowrap;
                color: #f1c40f;
                font-weight: bold;
            }

            td:nth-of-type(1)::before { content: "Apellido"; }
            td:nth-of-type(2)::before { content: "Nombre"; }
            td:nth-of-type(3)::before { content: "Fecha"; }
            td:nth-of-type(4)::before { content: "Hora"; }
        }
    </style>
</head>
<body>

<h2>Asistencias del Día</h2>
<div class="info">Gimnasio: <?= $gimnasio_id ?> | Fecha: <?= $hoy ?></div>

<?php if (!$resultado || $resultado->num_rows === 0): ?>
    <div class="no-result">Sin resultados para hoy.</div>
<?php else: ?>
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
            <?php while ($row = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['apellido']) ?></td>
                    <td><?= htmlspecialchars($row['nombre']) ?></td>
                    <td><?= htmlspecialchars($row['fecha']) ?></td>
                    <td><?= htmlspecialchars($row['hora']) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
