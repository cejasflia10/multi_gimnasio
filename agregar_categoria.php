<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Categoría</title>
  
</head>
<script src="fullscreen.js"></script>

<body>
    <div class="contenedor">

  <div class="container">
    <h1>Agregar Nueva Categoría</h1>
    <form action="guardar_categoria.php" method="POST">
      <label for="nombre">Nombre de la Categoría:</label>
      <input type="text" name="nombre" id="nombre" required>
      <button type="submit">Guardar Categoría</button>
    </form>
    <a href="ver_categorias.php">Volver</a>
  </div>
  </div>

</body>
</html>
