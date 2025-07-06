<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$resultado = $conexion->query("
    SELECT c.apellido, c.nombre, m.fecha, m.hora
    FROM asistencias m
    JOIN clientes c ON m.cliente_id = c.id
    WHERE c.gimnasio_id = $gimnasio_id
    ORDER BY m.fecha DESC, m.hora DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Asistencias por QR</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
  <h2>📋 Asistencias Registradas por QR</h2>

  <table>
    <thead>
      <tr>
        <th>Apellido</th>
        <th>Nombre</th>
        <th>Fecha</th>
        <th>Hora</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($resultado && $resultado->num_rows > 0): ?>
        <?php while ($row = $resultado->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['apellido']) ?></td>
            <td><?= htmlspecialchars($row['nombre']) ?></td>
            <td><?= $row['fecha'] ?></td>
            <td><?= $row['hora'] ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="4">No hay asistencias registradas.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>
