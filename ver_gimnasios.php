<?php
include 'conexion.php';
include 'menu_horizontal.php';


$resultado = $conexion->query("SELECT * FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gimnasios Registrados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
  <h1 class="titulo-seccion">🏋️ Gimnasios Registrados</h1>

  <div class="tabla-contenedor">
    <table>
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Email</th>
          <th>Clientes</th>
          <th>Plan</th>
          <th>Vencimiento</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['nombre']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td><?= htmlspecialchars($row['cantidad_clientes']) ?></td>
          <td><?= htmlspecialchars($row['plan']) ?></td>
          <td><?= htmlspecialchars($row['fecha_vencimiento']) ?></td>
          <td>
            <a class="btn-editar" href="editar_gimnasio.php?id=<?= $row['id'] ?>">✏️ Editar</a>
            <a class="btn-eliminar" href="eliminar_gimnasio.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Estás seguro de que deseas eliminar este gimnasio?')">🗑️ Eliminar</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
