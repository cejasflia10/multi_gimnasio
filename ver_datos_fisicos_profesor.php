
<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$datos = $conexion->query("
    SELECT d.fecha, d.peso, d.altura, d.talle_remera, d.talle_pantalon, d.talle_calzado, d.observaciones, c.apellido, c.nombre
    FROM datos_fisicos d
    JOIN clientes c ON d.cliente_id = c.id
    WHERE d.profesor_id = $profesor_id
    ORDER BY d.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Datos Físicos de Alumnos</title>
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

<h1>📋 Datos Físicos de Alumnos</h1>

<?php if ($datos->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Alumno</th>
            <th>Fecha</th>
            <th>Peso</th>
            <th>Altura</th>
            <th>Remera</th>
            <th>Pantalón</th>
            <th>Calzado</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($d = $datos->fetch_assoc()): ?>
        <tr>
            <td><?= $d['apellido'] ?>, <?= $d['nombre'] ?></td>
            <td><?= $d['fecha'] ?></td>
            <td><?= $d['peso'] ?> kg</td>
            <td><?= $d['altura'] ?> cm</td>
            <td><?= $d['talle_remera'] ?></td>
            <td><?= $d['talle_pantalon'] ?></td>
            <td><?= $d['talle_calzado'] ?></td>
            <td><?= $d['observaciones'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
    <p style="text-align: center;">No hay datos físicos registrados.</p>
<?php endif; ?>

</body>
</html>
