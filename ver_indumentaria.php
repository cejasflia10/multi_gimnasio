<?php
include 'conexion.php';
include 'menu_horizontal.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$result = $conexion->query("SELECT * FROM indumentaria WHERE gimnasio_id = $gimnasio_id ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Indumentaria</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
  <h1>🧥 Indumentaria</h1>

  <div class="tabla-scroll">
  <table class="tabla">
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
        <td><?= htmlspecialchars($row['nombre']) ?></td>
        <td><?= htmlspecialchars($row['talle']) ?></td>
        <td>$<?= number_format($row['precio_compra'], 2) ?></td>
        <td>$<?= number_format($row['precio_venta'], 2) ?></td>
        <td><?= $row['stock'] ?></td>
        <td class="acciones">
          <a href="editar_indumentaria.php?id=<?= $row['id'] ?>" class="boton-naranja">✏️</a>
          <a href="eliminar_indumentaria.php?id=<?= $row['id'] ?>" class="boton-rojo" onclick="return confirm('¿Eliminar este producto?')">🗑️</a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  </div>
</div>
</body>
</html>
