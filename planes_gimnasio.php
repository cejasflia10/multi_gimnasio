<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$mensaje = "";

// Agregar plan
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $clientes_permitidos = intval($_POST['clientes_permitidos'] ?? 0);

    if (!empty($nombre) && $precio > 0 && $clientes_permitidos > 0) {
        $stmt = $conexion->prepare("INSERT INTO planes_gimnasio (nombre, precio, clientes_permitidos) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $nombre, $precio, $clientes_permitidos);

        if ($stmt->execute()) {
            $mensaje = "✅ Plan agregado correctamente.";
        } else {
            $mensaje = "❌ Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $mensaje = "⚠️ Todos los campos son obligatorios y válidos.";
    }
}

// Obtener todos los planes
$planes = $conexion->query("SELECT * FROM planes_gimnasio ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configurar Planes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h1>🛠️ Configurar Planes de Gimnasios</h1>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="nombre">📋 Nombre del Plan:</label>
        <input type="text" name="nombre" required>

        <label for="precio">💲 Precio:</label>
        <input type="number" name="precio" step="0.01" required>

        <label for="clientes_permitidos">👥 Clientes permitidos:</label>
        <input type="number" name="clientes_permitidos" required>

        <input type="submit" value="Guardar Plan">
    </form>

    <h2 style="text-align:center; margin-top:40px;">📑 Planes Registrados</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Precio</th>
            <th>Clientes Permitidos</th>
            <th>Acciones</th>
        </tr>
        <?php while($plan = $planes->fetch_assoc()): ?>
        <tr>
            <td><?= $plan['id'] ?></td>
            <td><?= htmlspecialchars($plan['nombre']) ?></td>
            <td>$<?= number_format($plan['precio'], 2) ?></td>
            <td><?= $plan['clientes_permitidos'] ?></td>
            <td>
                <a class="boton" href="editar_plan_gimnasio.php?id=<?= $plan['id'] ?>">✏️ Editar</a>
                <a class="boton" href="eliminar_plan_gimnasio.php?id=<?= $plan['id'] ?>" onclick="return confirm('¿Eliminar este plan?')">🗑️ Eliminar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

    <div class="volver">
        <a href="index.php">← Volver al panel</a>
    </div>
</div>
</body>
</html>
