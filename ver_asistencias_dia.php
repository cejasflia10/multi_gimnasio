<?php
include 'conexion.php';
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

$hoy = date('Y-m-d');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// CLIENTES (usa campo 'hora')
$clientes_q = $conexion->query("
  SELECT c.apellido, c.nombre, a.hora
  FROM asistencias_clientes a
  JOIN clientes c ON a.cliente_id = c.id
  WHERE a.fecha = '$hoy' AND c.gimnasio_id = $gimnasio_id
");

// PROFESORES (usa hora_entrada y hora_salida)
$profesores_q = $conexion->query("
  SELECT p.apellido, p.nombre, a.hora_entrada, a.hora_salida
  FROM asistencias_profesor a
  JOIN profesores p ON a.profesor_id = p.id
  WHERE a.fecha = '$hoy' AND a.id_gimnasio = $gimnasio_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Asistencias del Día</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background: #000;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
      text-align: center;
    }
    h1 {
      color: gold;
    }
    table {
      width: 95%;
      margin: 10px auto;
      border-collapse: collapse;
    }
    th, td {
      border: 1px solid gold;
      padding: 6px;
    }
    th {
      background: #222;
    }
  </style>
</head>
<body>
  <h1>Asistencias del Día - <?php echo date('d/m/Y'); ?></h1>

  <h2>Clientes</h2>
  <table>
    <tr><th>Apellido</th><th>Nombre</th><th>Hora Ingreso</th></tr>
    <?php if ($clientes_q->num_rows > 0): ?>
      <?php while ($c = $clientes_q->fetch_assoc()): ?>
        <tr>
          <td><?php echo $c['apellido']; ?></td>
          <td><?php echo $c['nombre']; ?></td>
          <td><?php echo $c['hora']; ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="3">Sin registros</td></tr>
    <?php endif; ?>
  </table>

  <h2>Profesores</h2>
  <table>
    <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Egreso</th></tr>
    <?php if ($profesores_q->num_rows > 0): ?>
      <?php while ($p = $profesores_q->fetch_assoc()): ?>
        <tr>
          <td><?php echo $p['apellido']; ?></td>
          <td><?php echo $p['nombre']; ?></td>
          <td><?php echo $p['hora_entrada']; ?></td>
          <td><?php echo $p['hora_salida'] ?: '-'; ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="4">Sin registros</td></tr>
    <?php endif; ?>
  </table>
</body>
</html>
