<?php
include 'conexion.php';
$result = $conexion->query("SELECT * FROM suplementos");
?>
<table>
  <tr><th>Nombre</th><th>Tipo</th><th>Compra</th><th>Venta</th><th>Stock</th><th>Acciones</th></tr>
  <?php while($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?= $row['nombre'] ?></td>
      <td><?= $row['tipo'] ?></td>
      <td><?= $row['precio_compra'] ?></td>
      <td><?= $row['precio_venta'] ?></td>
      <td><?= $row['stock'] ?></td>
      <td><a href="editar_suplemento.php?id=<?= $row['id'] ?>">Editar</a></td>
    </tr>
  <?php endwhile; ?>
</table>
