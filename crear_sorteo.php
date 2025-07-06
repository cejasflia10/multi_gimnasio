<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

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
    <title>Crear Sorteo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">🎁 Crear Nuevo Sorteo</h2>

    <form action="guardar_sorteo.php" method="POST" class="formulario">
        <label>Título:</label>
        <input type="text" name="titulo" required>

        <label>Descripción:</label>
        <textarea name="descripcion" rows="4" required></textarea>

        <label>Premio:</label>
        <input type="text" name="premio" required>

        <label>Fecha del sorteo:</label>
        <input type="date" name="fecha" required>

        <button type="submit">Guardar Sorteo</button>
    </form>
</div>
</body>
</html>
