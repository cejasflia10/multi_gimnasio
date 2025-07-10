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

$id = intval($_GET['id'] ?? 0);
if ($id === 0) {
    echo "ID no válido.";
    exit;
}

// Obtener usuario actual
$usuario = $conexion->query("SELECT * FROM usuarios_gimnasio WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
if (!$usuario) {
    echo "Usuario no encontrado.";
    exit;
}

// Obtener planes disponibles
$planes = $conexion->query("SELECT id, nombre FROM planes_acceso WHERE gimnasio_id = $gimnasio_id");

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_completo']);
    $usuario_nuevo = trim($_POST['usuario']);
    $pass = trim($_POST['clave']);
    $plan_id = intval($_POST['plan_id']);

    if ($nombre && $usuario_nuevo) {
        $query = "UPDATE usuarios_gimnasio SET nombre_completo = ?, usuario = ?, plan_id = ?";
        $params = [$nombre, $usuario_nuevo, $plan_id];
        $types = "ssi";

        // Si se cambia la contraseña
        if (!empty($pass)) {
            $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
            $query .= ", clave = ?";
            $params[] = $pass_hash;
            $types .= "s";
        }

        $query .= " WHERE id = ? AND gimnasio_id = ?";
        $params[] = $id;
        $params[] = $gimnasio_id;
        $types .= "ii";

        $stmt = $conexion->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $mensaje = "<p style='color:lime;'>✅ Usuario actualizado correctamente.</p>";
    } else {
        $mensaje = "<p style='color:red;'>❌ Complete los campos requeridos.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <style>
        body { background: #111; color: gold; font-family: Arial; padding: 20px; }
        .formulario { background: #222; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; background: #000; color: white; border: 1px solid gold; }
        button { background: gold; color: black; padding: 10px 20px; font-weight: bold; margin-top: 20px; cursor: pointer; border: none; }
        a { color: gold; display: inline-block; margin-top: 10px; }
    </style>
</head>
<body>

<div class="formulario">
    <h2>✏️ Editar Usuario</h2>
    <?= $mensaje ?>
    <form method="post">
        <label>Nombre completo</label>
        <input type="text" name="nombre_completo" value="<?= htmlspecialchars($usuario['nombre_completo']) ?>" required>

        <label>Usuario</label>
        <input type="text" name="usuario" value="<?= htmlspecialchars($usuario['usuario']) ?>" required>

        <label>Nueva contraseña (dejar en blanco si no desea cambiarla)</label>
        <input type="password" name="clave">

        <label>Plan de acceso</label>
        <select name="plan_id" required>
            <?php while ($plan = $planes->fetch_assoc()): ?>
                <option value="<?= $plan['id'] ?>" <?= $plan['id'] == $usuario['plan_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($plan['nombre']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit">💾 Guardar cambios</button>
    </form>
    <a href="ver_usuarios_gimnasio.php">← Volver</a>
</div>

</body>
</html>
