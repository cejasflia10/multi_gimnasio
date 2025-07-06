<?php
session_start();
include 'conexion.php';

if (!isset($_GET['id'])) {
    echo "ID no válido.";
    exit;
}

$id = intval($_GET['id']);
$resultado = $conexion->query("SELECT * FROM categorias WHERE id = $id");

if (!$resultado || $resultado->num_rows == 0) {
    echo "Categoría no encontrada.";
    exit;
}

$categoria = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Categoría</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>✏️ Editar Categoría</h2>

    <form method="POST" action="guardar_edicion_categoria.php">
        <input type="hidden" name="id" value="<?= $categoria['id'] ?>">

        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" id="nombre" value="<?= htmlspecialchars($categoria['nombre']) ?>" required>

        <button type="submit">Guardar Cambios</button>
        <a href="ver_categorias.php" style="display:inline-block; margin-top:10px; color:#ffd600;">⬅ Cancelar</a>
    </form>
</div>
</body>
</html>
