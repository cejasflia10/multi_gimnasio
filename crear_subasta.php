<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Subasta</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">➕ Crear Nueva Subasta</h2>

    <form action="guardar_subasta.php" method="POST" enctype="multipart/form-data" class="formulario">
        <label>Título:</label>
        <input type="text" name="titulo" required>

        <label>Descripción:</label>
        <textarea name="descripcion" rows="4" required></textarea>

        <label>Precio base ($):</label>
        <input type="number" name="precio_base" step="0.01" min="0" required>

        <label>Fecha y hora de cierre:</label>
        <input type="datetime-local" name="fecha_cierre" required>

        <label>Imagen del producto:</label>
        <input type="file" name="imagen" accept="image/*">

        <button type="submit">Guardar Subasta</button>
    </form>
</div>
</body>
</html>
