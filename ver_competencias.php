<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$competencias = $conexion->query("
    SELECT c.fecha, c.evento, c.resultado, c.observaciones, cli.apellido, cli.nombre
    FROM competencias c
    JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.profesor_id = $profesor_id
    ORDER BY c.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Competencias</title>
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

<h1>🏆 Historial de Competencias</h1>

<?php if ($competencias->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Alumno</th>
            <th>Fecha</th>
            <th>Evento</th>
            <th>Resultado</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($c = $competencias->fetch_assoc()): ?>
        <tr>
            <td><?= $c['apellido'] ?>, <?= $c['nombre'] ?></td>
            <td><?= $c['fecha'] ?></td>
            <td><?= $c['evento'] ?></td>
            <td><?= $c['resultado'] ?></td>
            <td><?= $c['observaciones'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
    <p style="text-align: center;">No hay competencias registradas.</p>
<?php endif; ?>

</body>
</html>
