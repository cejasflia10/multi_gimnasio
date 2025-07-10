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

// Detectar columnas existentes
$tiene_usuario = $tiene_nombre = $tiene_apellido = false;
$columnas = $conexion->query("SHOW COLUMNS FROM usuarios_gimnasio");
while ($col = $columnas->fetch_assoc()) {
    if ($col['Field'] == 'usuario') $tiene_usuario = true;
    if ($col['Field'] == 'nombre') $tiene_nombre = true;
    if ($col['Field'] == 'apellido') $tiene_apellido = true;
}

$select_usuario = $tiene_usuario ? "u.usuario AS nombre_usuario" : "'' AS nombre_usuario";
$select_nombre = ($tiene_nombre && $tiene_apellido)
    ? "CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo"
    : ($tiene_nombre ? "u.nombre AS nombre_completo" : "'' AS nombre_completo");

$query = "
    SELECT u.id, $select_usuario, $select_nombre, p.nombre AS plan
    FROM usuarios_gimnasio u
    LEFT JOIN planes_acceso p ON u.plan_id = p.id
    WHERE u.gimnasio_id = $gimnasio_id
    ORDER BY nombre_completo
";
$usuarios = $conexion->query($query);
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
        .editar { background: gold; color: black; padding: 5px 10px; border: none; cursor: pointer; text-decoration: none; }
    </style>
</head>
<body>

<h2>👥 Usuarios del Gimnasio</h2>
<?= $mensaje ?>

<?php if ($usuarios && $usuarios->num_rows > 0): ?>
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
        <td><?= htmlspecialchars($row['nombre_usuario']) ?></td>
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
<?php else: ?>
    <p style="color:orange;">No hay usuarios registrados para este gimnasio.</p>
<?php endif; ?>

</body>
</html>
