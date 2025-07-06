<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$archivos = $conexion->query("
    SELECT a.id, a.tipo, a.archivo, a.fecha, c.apellido, c.nombre
    FROM archivos_profesor a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.profesor_id = $profesor_id
    ORDER BY a.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Archivos Cargados</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">

</head>
<body>
<div class="contenedor">
<h1>📂 Archivos Subidos</h1>

<?php if ($archivos->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Alumno</th>
            <th>Tipo</th>
            <th>Archivo</th>
            <th>Fecha</th>
            <th>Descargar</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($a = $archivos->fetch_assoc()): ?>
        <tr>
            <td><?= $a['apellido'] ?>, <?= $a['nombre'] ?></td>
            <td><?= $a['tipo'] ?></td>
            <td><?= basename($a['archivo']) ?></td>
            <td><?= $a['fecha'] ?></td>
            <td><a href="<?= $a['archivo'] ?>" target="_blank">Ver</a></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
    <p style="text-align: center;">No hay archivos cargados.</p>
<?php endif; ?>
</div>
</body>
</html>
