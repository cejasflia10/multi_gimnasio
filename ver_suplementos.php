<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$sql = "SELECT s.*, c.nombre AS categoria
        FROM suplementos s
        LEFT JOIN categorias c ON s.categoria_id = c.id
        WHERE s.gimnasio_id = $gimnasio_id
        ORDER BY s.nombre ASC";
$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ver Suplementos</title>
  <style>
    body {
      background-color: #000;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      border: 1px solid gold;
      padding: 10px;
      text-align: center;
    }
    th {
      background-color: #111;
    }
    a {
      background-color: gold;
      color: black;
      padding: 6px 10px;
      text-decoration: none;
      border-radius: 4px;
      margin: 2px;
      display: inline-block;
    }
  </style>
</head>
<body>
  <h1>Lista de Suplementos</h1>
  <a href="agregar_suplemento.php">Agregar Suplemento</a>
  <table>
    <tr>
      <th>Nombre</th>
      <th>Tipo</th>
      <th>Compra</th>
      <th>Venta</th>
      <th>Stock</th>
      <th>Categoría</th>
    </tr>
    <?php while ($row = $resultado->fetch_assoc()): ?>
      <tr>
        <td><?= $row['nombre'] ?></td>
        <td><?= $row['tipo'] ?></td>
        <td>$<?= number_format($row['precio_compra'], 2) ?></td>
        <td>$<?= number_format($row['precio_venta'], 2) ?></td>
        <td><?= $row['stock'] ?></td>
        <td><?= $row['categoria'] ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</body>
</html>
