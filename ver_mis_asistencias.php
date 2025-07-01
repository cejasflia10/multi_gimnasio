
<?php
session_start();
include 'conexion.php';
$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");
include 'menu_cliente.php';

// Obtener asistencias
$asistencias = $conexion->query("
    SELECT fecha, hora 
    FROM asistencias 
    WHERE cliente_id = $cliente_id 
    ORDER BY fecha DESC, hora DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🧾 Mis Asistencias</title>
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
    <h1>🧾 Mis Asistencias</h1>
    <?php if ($asistencias->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $asistencias->fetch_assoc()): ?>
                    <tr>
                        <td><?= $fila['fecha'] ?></td>
                        <td><?= $fila['hora'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center;">Aún no se registran asistencias.</p>
    <?php endif; ?>
</body>
</html>
