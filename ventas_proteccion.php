<?php include 'conexion.php'; ?>
include 'menu_horizontal.php';

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ventas de Protecciones</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      padding-left: 260px;
    }
    h1 {
      color: #ffc107;
      padding: 20px;
    }
    table {
      width: 90%;
      margin: 20px auto;
      border-collapse: collapse;
      background-color: #222;
    }
    th, td {
      padding: 10px;
      border: 1px solid #444;
      text-align: center;
    }
    th {
      background-color: #333;
      color: #ffc107;
    }
    form {
      width: 90%;
      margin: 20px auto;
      display: flex;
      gap: 10px;
    }
    input, select {
      padding: 8px;
    }
    .btn {
      background-color: #ffc107;
      border: none;
      padding: 8px 12px;
      cursor: pointer;
      color: #000;
    }
  </style>
</head>
<body>

<h1>Ventas de Protecciones</h1>

<table>
  <tr>
    <th>ID</th>
    <th>Nombre</th>
    <th>Talle/Oz</th>
    <th>Precio Compra</th>
    <th>Precio Venta</th>
    <th>Acciones</th>
  </tr>
  <?php
    $resultado = $conexion->query("SELECT * FROM productos_proteccion");
    while ($row = $resultado->fetch_assoc()) {
      echo "<tr>
        <td>{$row['id']}</td>
        <td>{$row['nombre']}</td>
        <td>{$row['talle']}</td>
        <td>\${$row['precio_compra']}</td>
        <td>\${$row['precio_venta']}</td>
        <td>
          <a href='editar_proteccion.php?id={$row['id']}' class='btn'>Editar</a>
          <a href='eliminar_proteccion.php?id={$row['id']}' class='btn' onclick='return confirm(\"¿Seguro que quieres eliminar?\")'>Eliminar</a>
        </td>
      </tr>";
    }
  ?>
</table>

<h2 style="text-align:center;">Agregar Nueva Protección</h2>
<form action="guardar_proteccion.php" method="POST">
  <input type="text" name="nombre" placeholder="Nombre" required>
  <input type="text" name="talle" placeholder="Talle/Oz" required>
  <input type="number" name="precio_compra" placeholder="Precio Compra" step="0.01" required>
  <input type="number" name="precio_venta" placeholder="Precio Venta" step="0.01" required>
  <button type="submit" class="btn">Agregar</button>
</form>

</body>
</html>
