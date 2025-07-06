<?php
include 'conexion.php';
include 'menu_horizontal.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Unificar productos de distintas tablas con categoría
$productos = $conexion->query("
    SELECT id, nombre, categoria AS tipo, compra AS precio_compra, venta AS precio_venta, stock
    FROM productos
    WHERE gimnasio_id = $gimnasio_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ver Productos</title>
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
  <h2>📦 Listado General de Productos</h2>

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
