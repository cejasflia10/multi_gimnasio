<?php
include 'conexion.php';
include 'menu_horizontal.php';

// Consulta actualizada: incluye cantidad de clientes y nombre del plan
$resultado = $conexion->query("
    SELECT g.*, 
           (SELECT COUNT(*) FROM clientes c WHERE c.gimnasio_id = g.id) AS cantidad_clientes,
           p.nombre AS nombre_plan
    FROM gimnasios g
    LEFT JOIN planes_gimnasio p ON g.plan_id = p.id
");
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
          <td><?= htmlspecialchars((string)($row['nombre'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($row['email'] ?? '')) ?></td>
          <td><?= $row['cantidad_clientes'] ?></td>
          <td><?= htmlspecialchars((string)($row['nombre_plan'] ?? 'Sin plan')) ?></td>
          <td><?= htmlspecialchars((string)($row['fecha_vencimiento'] ?? '')) ?></td>
          <td>
            <a class="btn-editar" href="editar_gimnasio.php?id=<?= $row['id'] ?>">✏️ Editar</a>
            <a class="btn-eliminar" href="eliminar_gimnasio.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Estás seguro de que deseas eliminar este gimnasio?')">🗑️ Eliminar</a>
            <a class="btn-renovar" href="renovar_gimnasio.php?id=<?= $row['id'] ?>">🔄 Renovar</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
