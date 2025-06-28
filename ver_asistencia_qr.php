<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$resultado = $conexion->query("SELECT c.apellido, c.nombre, a.fecha, a.hora
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE c.gimnasio_id = $gimnasio_id
    ORDER BY a.fecha DESC, a.hora DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Asistencias por QR</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #1c1c1c;
    }
    th, td {
      padding: 10px;
      border: 1px solid gold;
      text-align: center;
    }
    th {
      background-color: #222;
    }
    tr:hover {
      background-color: #333;
    }
    @media screen and (max-width: 768px) {
      table, thead, tbody, th, td, tr {
        font-size: 14px;
      }
    }
  </style>
</head>
<body>

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

</body>
</html>
