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
    <meta charset="UTF-8">
    <title>Configurar Accesos por Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #444; text-align: center; }
        th { background-color: #222; }
        tr:nth-child(even) { background-color: #1a1a1a; }
        .boton { padding: 6px 10px; background: gold; color: black; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .boton:hover { background: #ffd700; }
    </style>
</head>
<body>

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

</body>
</html>
