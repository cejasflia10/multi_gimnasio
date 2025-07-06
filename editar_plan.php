<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['id'])) {
    die("ID de plan no especificado.");
}

$id = intval($_GET['id']);
$query = "SELECT * FROM planes WHERE id = $id";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Plan no encontrado.");
}

$plan = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h2>✏️ Editar Plan</h2>

    <form action="guardar_plan.php" method="post">
        <input type="hidden" name="id" value="<?= $plan['id'] ?>">

        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($plan['nombre'] ?? '') ?>" required>

        <label for="precio">Precio:</label>
        <input type="text" name="precio" value="<?= htmlspecialchars($plan['precio'] ?? '') ?>" required>

        <label for="dias_disponibles">Días disponibles:</label>
        <input type="number" name="dias_disponibles" value="<?= htmlspecialchars($plan['dias_disponibles'] ?? '') ?>" required>

        <label for="duracion_meses">Duración (meses):</label>
        <input type="number" name="duracion_meses" value="<?= htmlspecialchars($plan['duracion_meses'] ?? '') ?>" required>

        <button type="submit">Guardar cambios</button>
    </form>

    <br>
    <a href="planes.php" style="color:#ffd600;">⬅ Volver al listado</a>
</div>

</body>
</html>
