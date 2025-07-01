
<?php
session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");

$graduaciones = $conexion->query("
    SELECT g.fecha, g.disciplina, g.nivel, g.observaciones, p.apellido AS profesor
    FROM graduaciones g
    JOIN profesores p ON g.profesor_id = p.id
    WHERE g.cliente_id = $cliente_id
    ORDER BY g.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Graduaciones</title>
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

<h1>🎓 Mis Graduaciones</h1>

<?php if ($graduaciones->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Disciplina</th>
            <th>Nivel</th>
            <th>Profesor</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($g = $graduaciones->fetch_assoc()): ?>
        <tr>
            <td><?= $g['fecha'] ?></td>
            <td><?= $g['disciplina'] ?></td>
            <td><?= $g['nivel'] ?></td>
            <td><?= $g['profesor'] ?></td>
            <td><?= $g['observaciones'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
    <p style="text-align: center;">Aún no tenés graduaciones registradas.</p>
<?php endif; ?>

</body>
</html>
