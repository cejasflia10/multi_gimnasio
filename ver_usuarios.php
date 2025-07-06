<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("conexion.php");
include("menu_horizontal.php");

$resultado = $conexion->query("SELECT u.id, u.usuario, u.rol, u.gimnasio_id, g.nombre AS gimnasio 
                               FROM usuarios u 
                               LEFT JOIN gimnasios g ON u.gimnasio_id = g.id 
                               ORDER BY u.id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios del Sistema</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>👤 Usuarios del Sistema</h2>

    <a href="agregar_usuario.php" class="boton">➕ Agregar Nuevo Usuario</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>ID Gimnasio</th>
                <th>Gimnasio</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= $fila['id'] ?></td>
                <td><?= htmlspecialchars($fila['usuario']) ?></td>
                <td><?= $fila['rol'] ?></td>
                <td><?= $fila['gimnasio_id'] ?></td>
                <td><?= htmlspecialchars($fila['gimnasio'] ?? '—') ?></td>
                <td class="acciones">
                    <a href="editar_usuario.php?id=<?= $fila['id'] ?>">✏️ Editar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
