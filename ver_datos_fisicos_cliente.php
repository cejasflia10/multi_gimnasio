<?php
session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");

$datos = $conexion->query("
    SELECT d.fecha, d.peso, d.altura, d.talle_remera, d.talle_pantalon, d.talle_calzado, d.observaciones, p.apellido AS profesor
    FROM datos_fisicos d
    JOIN profesores p ON d.profesor_id = p.id
    WHERE d.cliente_id = $cliente_id
    ORDER BY d.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Datos Físicos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
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
        th { background-color: #222; }
    </style>
</head>
<body>

<h1>🧍 Mis Datos Físicos</h1>

<?php if ($datos->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Peso</th>
            <th>Altura</th>
            <th>Remera</th>
            <th>Pantalón</th>
            <th>Calzado</th>
            <th>Profesor</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($d = $datos->fetch_assoc()): ?>
        <tr>
            <td><?= $d['fecha'] ?></td>
            <td><?= $d['peso'] ?> kg</td>
            <td><?= $d['altura'] ?> cm</td>
            <td><?= $d['talle_remera'] ?></td>
            <td><?= $d['talle_pantalon'] ?></td>
            <td><?= $d['talle_calzado'] ?></td>
            <td><?= $d['profesor'] ?></td>
            <td><?= $d['observaciones'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
    <p style="text-align: center;">No hay datos registrados aún.</p>
<?php endif; ?>

</body>
</html>
