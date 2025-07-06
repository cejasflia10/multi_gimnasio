<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('configuraciones')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

$gimnasios = $conexion->query("SELECT * FROM gimnasios ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
        <link rel="stylesheet" href="estilo_unificado.css">
    <meta charset="UTF-8">
    <title>Configurar Accesos por Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
</head>
<body>
<div class="contenedor">

    <h1>⚙️ Configurar Accesos por Gimnasio</h1>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Panel</th>
                <th>Ventas</th>
                <th>Asistencias</th>
                <th>Usuarios</th>
                <th>Editar</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($g = $gimnasios->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($g['nombre']) ?></td>
                <td><?= $g['acceso_panel'] ? '✅' : '❌' ?></td>
                <td><?= $g['acceso_ventas'] ? '✅' : '❌' ?></td>
                <td><?= $g['acceso_asistencias'] ? '✅' : '❌' ?></td>
                <td><?= $g['acceso_usuarios'] ? '✅' : '❌' ?></td>
                <td><a class="boton" href="editar_accesos.php?id=<?= $g['id'] ?>">Editar</a></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
