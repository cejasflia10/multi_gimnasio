<?php
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$result = $conexion->query("SELECT * FROM indumentaria WHERE gimnasio_id = $gimnasio_id ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ver Indumentaria</title>
  <style>
    body {
      background: #000;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }

    .container {
      padding: 30px 20px;
      max-width: 1200px;
      margin: 0 auto;
    }

    h1 {
      text-align: center;
      color: #ffc107;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      overflow-x: auto;
    }

    th, td {
      padding: 10px;
      border-bottom: 1px solid #333;
      text-align: center;
    }

    th {
      color: #ffc107;
    }

    .btn {
      padding: 5px 10px;
      background: #ffc107;
      color: #111;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      text-decoration: none;
      margin: 0 3px;
      display: inline-block;
    }

    @media screen and (max-width: 768px) {
      table, thead, tbody, th, td, tr {
        display: block;
        width: 100%;
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
        text-align: left;
        padding: 10px;
        border: none;
        border-bottom: 1px solid #333;
      }

      td:before {
        content: attr(data-label);
        font-weight: bold;
        color: #ffc107;
        display: block;
        margin-bottom: 5px;
      }
    }
  </style>
</head>
<script src="fullscreen.js"></script>

<body>

<div class="container">
  <h1>Indumentaria</h1>

  <table>
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Talle</th>
        <th>Compra</th>
        <th>Venta</th>
        <th>Stock</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while($row = $result->fetch_assoc()): ?>
      <tr>
        <td data-label="Nombre"><?= $row['nombre'] ?></td>
        <td data-label="Talle"><?= $row['talle'] ?></td>
        <td data-label="Compra">$<?= number_format($row['precio_compra'], 2) ?></td>
        <td data-label="Venta">$<?= number_format($row['precio_venta'], 2) ?></td>
        <td data-label="Stock"><?= $row['stock'] ?></td>
        <td data-label="Acciones">
          <a class="btn" href="editar_indumentaria.php?id=<?= $row['id'] ?>">Editar</a>
          <a class="btn" href="eliminar_indumentaria.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar este producto?')">Eliminar</a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

</body>
</html>
