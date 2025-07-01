
<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$alumnos = $conexion->query("
    SELECT DISTINCT c.apellido, c.nombre, c.dni, c.disciplina
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.id_profesor = $profesor_id
    ORDER BY c.apellido
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Alumnos</title>
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

<h1>👨‍🏫 Alumnos Asignados</h1>

<?php if ($alumnos->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Disciplina</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($a = $alumnos->fetch_assoc()): ?>
        <tr>
            <td><?= $a['apellido'] ?></td>
            <td><?= $a['nombre'] ?></td>
            <td><?= $a['dni'] ?></td>
            <td><?= $a['disciplina'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
    <p style="text-align: center;">No se encontraron alumnos asignados.</p>
<?php endif; ?>

</body>
</html>
