<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Profesores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { background: #111; color: gold; font-family: Arial; padding: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid gold; padding: 10px; text-align: center; }
    th { background-color: #222; }
  </style>
</head>
<body>
<h2>📋 Listado de Profesores</h2>

<table>
  <tr>
    <th>Apellido</th>
    <th>Nombre</th>
    <th>DNI</th>
    <th>Teléfono</th>
    <th>Acción</th>
  </tr>
  <?php while ($p = $resultado->fetch_assoc()): ?>
    <tr>
      <td><?= $p['apellido'] ?></td>
      <td><?= $p['nombre'] ?></td>
      <td><?= $p['dni'] ?></td>
      <td><?= $p['telefono'] ?></td>
      <td><a href="panel_profesor.php?id=<?= $p['id'] ?>">Ver Panel</a></td>
    </tr>
  <?php endwhile; ?>
</table>

</body>
</html>
