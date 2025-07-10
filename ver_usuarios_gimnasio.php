<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$mensaje = "";

// Eliminar usuario si se envió por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    $eliminar_id = intval($_POST['eliminar_id']);
    $conexion->query("DELETE FROM usuarios_gimnasio WHERE id = $eliminar_id AND gimnasio_id = $gimnasio_id");
    $mensaje = "<p style='color:lime;'>✅ Usuario eliminado correctamente.</p>";
}

// Obtener todos los usuarios del gimnasio
$usuarios = $conexion->query("
    SELECT u.id, u.usuario, u.nombre_completo, p.nombre AS plan
    FROM usuarios_gimnasio u
    LEFT JOIN planes_acceso p ON u.plan_id = p.id
    WHERE u.gimnasio_id = $gimnasio_id
    ORDER BY u.nombre_completo
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios del Gimnasio</title>
    <style>
        body { background: #111; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; background: #222; color: white; margin-top: 20px; }
        th, td { border: 1px solid gold; padding: 10px; text-align: center; }
        form { display: inline; }
        .boton { background: red; color: white; padding: 5px 10px; border: none; cursor: pointer; }
        .editar { background: gold; color: black; padding: 5px 10px; border: none; cursor: pointer; }
    </style>
</head>
<body>

<h2>👥 Usuarios del Gimnasio</h2>
<?= $mensaje ?>

<table>
    <tr>
        <th>Nombre completo</th>
        <th>Usuario</th>
        <th>Plan de acceso</th>
        <th>Acciones</th>
    </tr>
    <?php while ($row = $usuarios->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['nombre_completo']) ?></td>
        <td><?= htmlspecialchars($row['usuario']) ?></td>
        <td><?= htmlspecialchars($row['plan']) ?></td>
        <td>
            <form method="post" onsubmit="return confirm('¿Seguro que desea eliminar este usuario?');">
                <input type="hidden" name="eliminar_id" value="<?= $row['id'] ?>">
                <button type="submit" class="boton">Eliminar</button>
            </form>
            <a href="editar_usuario_gimnasio.php?id=<?= $row['id'] ?>" class="editar">Editar</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
