<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Categoría</title>
  <style>
    body {
      background-color: #000;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    .container {
      max-width: 600px;
      margin: auto;
      padding: 20px;
    }
    h1 {
      text-align: center;
    }
    label, input, button {
      display: block;
      width: 100%;
      margin-top: 10px;
      padding: 10px;
      font-size: 16px;
      border-radius: 6px;
    }
    input {
      background-color: #111;
      color: gold;
      border: 1px solid gold;
    }
    button {
      background-color: gold;
      color: black;
      border: none;
      margin-top: 20px;
      font-weight: bold;
      cursor: pointer;
    }
    a {
      display: inline-block;
      margin-top: 20px;
      color: gold;
      text-decoration: none;
    }
  </style>
</head>
<script src="fullscreen.js"></script>

<body>
  <div class="container">
    <h1>Agregar Nueva Categoría</h1>
    <form action="guardar_categoria.php" method="POST">
      <label for="nombre">Nombre de la Categoría:</label>
      <input type="text" name="nombre" id="nombre" required>
      <button type="submit">Guardar Categoría</button>
    </form>
    <a href="ver_categorias.php">Volver</a>
  </div>
</body>
</html>
