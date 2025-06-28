<?php
include 'conexion.php';
include 'menu_horizontal.php';

$resultado = $conexion->query("SELECT * FROM productos_proteccion ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ventas de Protecciones</title>
  <style>
    body {
      background-color: #000;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 30px 20px;
    }

    h1, h2 {
      color: #ffc107;
      text-align: center;
      margin-bottom: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
    }

    th, td {
      padding: 10px;
      border: 1px solid #444;
      text-align: center;
    }

    th {
      background-color: #222;
      color: #ffc107;
    }

    .btn {
      background-color: #ffc107;
      border: none;
      padding: 8px 12px;
      cursor: pointer;
      color: #000;
      border-radius: 5px;
      text-decoration: none;
      margin: 0 2px;
      display: inline-block;
    }

    form {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: center;
    }

    input {
      padding: 8px;
      border-radius: 5px;
      border: none;
      width: 200px;
    }

    @media screen and (max-width: 768px) {
      table, thead, tbody, th, td, tr {
        display: block;
      }

      thead tr {
        display: none;
      }

      tr {
        margin-bottom: 15px;
        background-color: #222;
        border-radius: 5px;
        padding: 10px;
      }

      td {
        border: none;
        border-bottom: 1px solid #444;
        text-align: left;
        padding: 10px;
      }

      td:before {
        content: attr(data-label);
        font-weight: bold;
        display: block;
        color: #ffc107;
        margin-bottom: 5px;
      }

      form {
        flex-direction: column;
        align-items: center;
      }

      input {
        width: 90%;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <h1>Ventas de Protecciones</h1>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Talle/Oz</th>
        <th>Precio Compra</th>
        <th>Precio Venta</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
          <td data-label="ID"><?= $row['id'] ?></td>
          <td data-label="Nombre"><?= $row['nombre'] ?></td>
          <td data-label="Talle"><?= $row['talle'] ?></td>
          <td data-label="Compra">$<?= number_format($row['precio_compra'], 2) ?></td>
          <td data-label="Venta">$<?= number_format($row['precio_venta'], 2) ?></td>
          <td data-label="Acciones">
            <a href="editar_proteccion.php?id=<?= $row['id'] ?>" class="btn">Editar</a>
            <a href="eliminar_proteccion.php?id=<?= $row['id'] ?>" class="btn" onclick="return confirm('¿Seguro que quieres eliminar?')">Eliminar</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <h2>Agregar Nueva Protección</h2>
  <form action="guardar_proteccion.php" method="POST">
    <input type="text" name="nombre" placeholder="Nombre" required>
    <input type="text" name="talle" placeholder="Talle/Oz" required>
    <input type="number" name="precio_compra" placeholder="Precio Compra" step="0.01" required>
    <input type="number" name="precio_venta" placeholder="Precio Venta" step="0.01" required>
    <button type="submit" class="btn">Agregar</button>
  </form>
</div>

</body>
</html>
