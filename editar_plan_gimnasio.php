<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = intval($_GET['id'] ?? 0);
$mensaje = "";

// Verificar que el plan pertenezca al gimnasio logueado
$consulta = $conexion->prepare("SELECT * FROM planes_gimnasio WHERE id = ? AND gimnasio_id = ?");
$consulta->bind_param("ii", $id, $gimnasio_id);
$consulta->execute();
$resultado = $consulta->get_result();
$plan = $resultado->fetch_assoc();
$consulta->close();

if (!$plan) {
    echo "<h2 style='color:red;'>❌ Plan no encontrado o no pertenece a este gimnasio.</h2>";
    exit;
}

// Actualizar plan
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $clientes = intval($_POST['clientes_permitidos']);

    if (!empty($nombre) && $precio > 0 && $clientes > 0) {
        $stmt = $conexion->prepare("UPDATE planes_gimnasio SET nombre = ?, precio = ?, clientes_permitidos = ? WHERE id = ? AND gimnasio_id = ?");
        $stmt->bind_param("sdiii", $nombre, $precio, $clientes, $id, $gimnasio_id);

        if ($stmt->execute()) {
            $mensaje = "✅ Plan actualizado correctamente.";
            $plan['nombre'] = $nombre;
            $plan['precio'] = $precio;
            $plan['clientes_permitidos'] = $clientes;
        } else {
            $mensaje = "❌ Error al actualizar: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Plan del Gimnasio</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h1>✏️ Editar Plan del Gimnasio</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="nombre">Nombre del Plan:</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($plan['nombre']) ?>" required>

        <label for="precio">Precio:</label>
        <input type="number" step="0.01" name="precio" value="<?= $plan['precio'] ?>" required>

        <label for="clientes_permitidos">Clientes Permitidos:</label>
        <input type="number" name="clientes_permitidos" value="<?= $plan['clientes_permitidos'] ?>" required>

        <input type="submit" value="Actualizar Plan">
    </form>

    <div class="volver">
        <a href="planes_gimnasio.php">← Volver a Planes</a>
    </div>
</div>
</body>
</html>
