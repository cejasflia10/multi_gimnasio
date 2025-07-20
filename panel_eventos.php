<?php
session_start();
if (!isset($_SESSION['evento_usuario_id'])) {
    header("Location: login_evento.php");
    exit;
}

include 'conexion.php';
include 'menu_eventos.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$resultado = $conexion->query("SELECT * FROM eventos_deportivos WHERE gimnasio_id = $gimnasio_id ORDER BY fecha DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Eventos Deportivos</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>🏆 Panel de Eventos Deportivos</h2>
    <a href="crear_evento.php" class="boton">➕ Nuevo Evento</a>
    <table>
        <tr>
            <th>Título</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Lugar</th>
            <th>Flyer</th>
            <th>Video</th>
            <th>Acciones</th>
        </tr>
        <?php while ($evento = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($evento['titulo']) ?></td>
            <td><?= $evento['fecha'] ?></td>
            <td><?= $evento['hora'] ?></td>
            <td><?= htmlspecialchars($evento['lugar']) ?></td>
            <td>
                <?php if ($evento['flyer']): ?>
                    <a href="<?= $evento['flyer'] ?>" target="_blank">📷 Ver</a>
                <?php else: ?>
                    ❌
                <?php endif; ?>
            </td>
            <td>
                <?php if ($evento['video']): ?>
                    <a href="<?= $evento['video'] ?>" target="_blank">▶️ Ver</a>
                <?php else: ?>
                    ❌
                <?php endif; ?>
            </td>
            <td>
                <a href="editar_evento.php?id=<?= $evento['id'] ?>">✏️</a>
                <a href="eliminar_evento.php?id=<?= $evento['id'] ?>" onclick="return confirm('¿Eliminar evento?')">🗑️</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
