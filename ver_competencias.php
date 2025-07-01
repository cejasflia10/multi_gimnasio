
<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");
include 'menu_cliente.php';

// Obtener competencias del cliente
$competencias = $conexion->query("
    SELECT fecha, evento, disciplina, resultado
    FROM competencias_cliente
    WHERE cliente_id = $cliente_id
    ORDER BY fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🥋 Mis Competencias</title>
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
    <h1>🥋 Mis Competencias</h1>
    <?php if ($competencias->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Evento</th>
                    <th>Disciplina</th>
                    <th>Resultado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($c = $competencias->fetch_assoc()): ?>
                    <tr>
                        <td><?= $c['fecha'] ?></td>
                        <td><?= $c['evento'] ?></td>
                        <td><?= $c['disciplina'] ?></td>
                        <td><?= $c['resultado'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center;">No se encontraron competencias registradas.</p>
    <?php endif; ?>
</body>
</html>
