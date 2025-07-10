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

// Obtener planes disponibles
$planes = $conexion->query("SELECT id, nombre FROM planes_acceso WHERE gimnasio_id = $gimnasio_id");

// Guardar nuevo usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_completo'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = trim($_POST['clave'] ?? '');
    $plan_id = intval($_POST['plan_id'] ?? 0);

    if ($nombre && $usuario && $clave && $plan_id > 0) {
        // Verificar usuario existente
        $existe = $conexion->query("SELECT id FROM usuarios_gimnasio WHERE usuario = '$usuario' AND gimnasio_id = $gimnasio_id")->num_rows;
        if ($existe > 0) {
            $mensaje = "<p style='color:red;'>❌ El nombre de usuario ya está en uso.</p>";
        } else {
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios_gimnasio (nombre_completo, usuario, clave, plan_id, gimnasio_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $nombre, $usuario, $hash, $plan_id, $gimnasio_id);
            $stmt->execute();
            $mensaje = "<p style='color:lime;'>✅ Usuario registrado correctamente.</p>";
        }
    } else {
        $mensaje = "<p style='color:red;'>❌ Complete todos los campos.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Usuario</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        .formulario { background: #222; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; background: #000; color: white; border: 1px solid gold; }
        button { background: gold; color: black; padding: 10px 20px; font-weight: bold; margin-top: 20px; cursor: pointer; border: none; }
        a { color: gold; display: inline-block; margin-top: 10px; }
    </style>
</head>
<body>

<div class="formulario">
    <h2>➕ Agregar Usuario de Gimnasio</h2>
    <?= $mensaje ?>
    <form method="post">
        <label>Nombre completo</label>
        <input type="text" name="nombre_completo" required>

        <label>Usuario</label>
        <input type="text" name="usuario" required>

        <label>Contraseña</label>
        <input type="password" name="clave" required>

        <label>Plan de acceso</label>
        <select name="plan_id" required>
            <option value="">Seleccionar...</option>
            <?php while ($plan = $planes->fetch_assoc()): ?>
                <option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['nombre']) ?></option>
            <?php endwhile; ?>
        </select>

        <button type="submit">💾 Guardar usuario</button>
    </form>
    <a href="ver_usuarios_gimnasio.php">← Volver</a>
</div>

</body>
</html>
