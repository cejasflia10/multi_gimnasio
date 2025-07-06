<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'permisos.php';

// Bloqueo si no tiene permiso
if (!tiene_permiso('configuraciones')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

$resultado = $conexion->query("SELECT * FROM plan_usuarios ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
        <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Configurar Planes de Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
</head>

<body>
<div class="contenedor">

    <h1>📋 Planes disponibles para Gimnasios</h1>

    <a href="agregar_plan.php" class="top-link">➕ Nuevo Plan</a>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Duración (días)</th>
                <th>Clientes Máx</th>
                <th>Precio ($)</th>
                <th>Acceso Panel</th>
                <th>Acceso Ventas</th>
                <th>Acceso Asistencias</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($fila['nombre']) ?></td>
                <td><?= $fila['duracion_dias'] ?></td>
                <td><?= $fila['limite_clientes'] ?></td>
                <td>$<?= number_format($fila['precio'], 2) ?></td>
                <td><?= $fila['acceso_panel'] ? '✅' : '❌' ?></td>
                <td><?= $fila['acceso_ventas'] ? '✅' : '❌' ?></td>
                <td><?= $fila['acceso_asistencias'] ? '✅' : '❌' ?></td>
                <td>
                    <a class="boton" href="editar_plan.php?id=<?= $fila['id'] ?>">✏️ Editar</a>
                    <a class="boton" href="eliminar_plan.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar este plan?')">🗑️ Eliminar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
