<?php
include 'conexion.php';
include 'menu_horizontal.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Unificar productos de distintas tablas con categoría
$productos = $conexion->query("
    SELECT id, nombre, 'Protección' AS tipo, precio_compra, precio_venta, stock FROM productos_proteccion WHERE gimnasio_id = $gimnasio_id
    UNION ALL
    SELECT id, nombre, 'Indumentaria' AS tipo, precio_compra, precio_venta, stock FROM productos_indumentaria WHERE gimnasio_id = $gimnasio_id
    UNION ALL
    SELECT id, nombre, 'Suplemento' AS tipo, precio_compra, precio_venta, stock FROM productos_suplemento WHERE gimnasio_id = $gimnasio_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ver Productos</title>
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

    h2 {
      color: #ffc107;
      text-align: center;
      margin-bottom: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    th, td {
      padding: 10px;
      border-bottom: 1px solid #444;
      text-align: center;
    }

    th {
      background-color: #111;
      color: #ffc107;
    }

    .btn {
      padding: 5px 10px;
      background: #ffc107;
      color: #000;
      border: none;
      border-radius: 5px;
      text-decoration: none;
      font-weight: bold;
      cursor: pointer;
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
        background-color: #111;
        padding: 10px;
        border-radius: 5px;
      }

      td {
        text-align: left;
        padding: 10px;
        border-bottom: 1px solid #333;
      }

      td:before {
        content: attr(data-label);
        font-weight: bold;
        display: block;
        color: #ffc107;
        margin-bottom: 5px;
      }
    }
  </style>
</head>
<script src="fullscreen.js"></script>

<body>

<div class="container">
  <h2>Listado General de Productos</h2>

  <table>
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Tipo</th>
        <th>Compra</th>
        <th>Venta</th>
        <th>Stock</th>
        <th>Acción</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($p = $productos->fetch_assoc()): ?>
        <tr>
          <td data-label="Nombre"><?= $p['nombre'] ?></td>
          <td data-label="Tipo"><?= $p['tipo'] ?></td>
          <td data-label="Compra">$<?= number_format($p['precio_compra'], 2) ?></td>
          <td data-label="Venta">$<?= number_format($p['precio_venta'], 2) ?></td>
          <td data-label="Stock"><?= $p['stock'] ?></td>
          <td data-label="Acción">
            <a href="editar_producto.php?id=<?= $p['id'] ?>&tipo=<?= strtolower($p['tipo']) ?>" class="btn">Editar</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

</body>
</html>
