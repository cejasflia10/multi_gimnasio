<?php
include 'conexion.php';
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

$hoy = date('Y-m-d');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// CLIENTES
$clientes_q = $conexion->query("
  SELECT c.apellido, c.nombre, a.hora_ingreso
  FROM asistencias_clientes a
  JOIN clientes c ON a.cliente_id = c.id
  WHERE a.fecha = '$hoy' AND c.gimnasio_id = $gimnasio_id
");

// PROFESORES
$profesores_q = $conexion->query("
  SELECT p.apellido, p.nombre, a.hora_ingreso, a.hora_salida
  FROM asistencias_profesor a
  JOIN profesores p ON a.profesor_id = p.id
  WHERE a.fecha = '$hoy' AND p.gimnasio_id = $gimnasio_id
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
      width: 90%;
      margin: 10px auto;
      border-collapse: collapse;
    }
    th, td {
      border: 1px solid gold;
      padding: 8px;
    }
    th {
      background-color: #222;
    }
  </style>
</head>
<body>
  <h1>Asistencias del Día - <?php echo date('d/m/Y'); ?></h1>

  <h2>Clientes</h2>
  <table>
    <tr><th>Apellido</th><th>Nombre</th><th>Hora Ingreso</th></tr>
    <?php while ($c = $clientes_q->fetch_assoc()): ?>
      <tr>
        <td><?php echo $c['apellido']; ?></td>
        <td><?php echo $c['nombre']; ?></td>
        <td><?php echo $c['hora_ingreso']; ?></td>
      </tr>
    <?php endwhile; ?>
  </table>

  <h2>Profesores</h2>
  <table>
    <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Egreso</th></tr>
    <?php while ($p = $profesores_q->fetch_assoc()): ?>
      <tr>
        <td><?php echo $p['apellido']; ?></td>
        <td><?php echo $p['nombre']; ?></td>
        <td><?php echo $p['hora_ingreso']; ?></td>
        <td><?php echo $p['hora_salida'] ?: '-'; ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</body>
</html>
