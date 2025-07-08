<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Asistencias de clientes (usar a.hora)
$res_clientes = $conexion->query("
    SELECT c.apellido, c.nombre, a.fecha, a.hora
    FROM asistencias_clientes a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.gimnasio_id = $gimnasio_id
    ORDER BY a.fecha DESC, a.hora DESC
");

// Asistencias de profesores (mantener hora_ingreso)
$res_profesores = $conexion->query("
    SELECT p.apellido, p.nombre, a.fecha, a.hora_ingreso AS hora
    FROM asistencias_profesores a
    JOIN profesores p ON a.profesor_id = p.id
    WHERE a.gimnasio_id = $gimnasio_id
    ORDER BY a.fecha DESC, a.hora_ingreso DESC
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
  <h2>📋 Asistencias de Clientes por QR</h2>
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
      <?php if ($res_clientes && $res_clientes->num_rows > 0): ?>
        <?php while ($row = $res_clientes->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['apellido']) ?></td>
            <td><?= htmlspecialchars($row['nombre']) ?></td>
            <td><?= $row['fecha'] ?></td>
            <td><?= $row['hora'] ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="4">No hay asistencias registradas de clientes.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h2>👨‍🏫 Asistencias de Profesores por QR</h2>
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
      <?php if ($res_profesores && $res_profesores->num_rows > 0): ?>
        <?php while ($row = $res_profesores->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['apellido']) ?></td>
            <td><?= htmlspecialchars($row['nombre']) ?></td>
            <td><?= $row['fecha'] ?></td>
            <td><?= $row['hora'] ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="4">No hay asistencias registradas de profesores.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>
