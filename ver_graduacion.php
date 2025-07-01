
<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");
include 'menu_cliente.php';

// Obtener graduaciones del cliente
$graduaciones = $conexion->query("
    SELECT fecha_examen, grado, disciplina
    FROM graduaciones_cliente
    WHERE cliente_id = $cliente_id
    ORDER BY fecha_examen DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🎓 Mi Graduación</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 { text-align: center; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
    </style>
</head>
<body>
    <h1>🎓 Mi Graduación</h1>
    <?php if ($graduaciones->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha de Examen</th>
                    <th>Grado</th>
                    <th>Disciplina</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($g = $graduaciones->fetch_assoc()): ?>
                    <tr>
                        <td><?= $g['fecha_examen'] ?></td>
                        <td><?= $g['grado'] ?></td>
                        <td><?= $g['disciplina'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center;">No se encontraron registros de graduación.</p>
    <?php endif; ?>
</body>
</html>
