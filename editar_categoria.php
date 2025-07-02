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
</head>
<body style="background-color: #111; color: gold; font-family: sans-serif; padding: 20px;">
    <h2>✏️ Editar Categoría</h2>

    <form method="POST" action="guardar_edicion_categoria.php">
        <input type="hidden" name="id" value="<?= $categoria['id'] ?>">
        <label for="nombre">Nombre:</label><br>
        <input type="text" name="nombre" id="nombre" value="<?= htmlspecialchars($categoria['nombre']) ?>" required style="padding:10px; width: 300px;"><br><br>
        <button type="submit" style="padding:10px 20px;">Guardar Cambios</button>
        <a href="ver_categorias.php" style="margin-left: 20px; color: white;">Cancelar</a>
    </form>
</body>
</html>
