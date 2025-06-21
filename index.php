<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$fecha_actual = date("Y-m-d");

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $query = "SELECT c.apellido, c.nombre, a.fecha, a.hora
              FROM asistencias_clientes a
              INNER JOIN clientes c ON c.id = a.cliente_id
              WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = CURDATE()
              ORDER BY a.fecha_hora DESC";
    $resultado = $conexion->query($query);
    return $resultado->fetch_all(MYSQLI_ASSOC);
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id, $fecha_actual) {
    $query = "SELECT p.apellido, p.nombre, r.fecha_hora, r.tipo
              FROM registro_profesores r
              INNER JOIN profesores p ON p.id = r.profesor_id
              WHERE r.gimnasio_id = $gimnasio_id AND DATE(r.fecha_hora) = '$fecha_actual'
              ORDER BY r.fecha_hora DESC";
    $resultado = $conexion->query($query);
    return $resultado->fetch_all(MYSQLI_ASSOC);
}

$asistencias_clientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$asistencias_profesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id, $fecha_actual);
?>

<?php include 'menu.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Principal - Fight Academy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background-color: #111;
      color: #fff;
      font-family: Arial, sans-serif;
      margin-left: 250px;
      padding: 20px;
    }
    .card {
      background-color: #222;
      border-radius: 10px;
      padding: 15px;
      margin-bottom: 15px;
      box-shadow: 0 0 10px #000;
    }
    h2 {
      color: gold;
    }
    table {
      width: 100%;
      background-color: #333;
      border-collapse: collapse;
    }
    th, td {
      border: 1px solid #444;
      padding: 8px;
      text-align: left;
    }
    th {
      background-color: #555;
    }
    @media screen and (max-width: 768px) {
      body {
        margin-left: 0;
        padding: 10px;
      }
    }
  </style>
</head>
<body>

<h2>Asistencias de Clientes - Hoy (<?php echo $fecha_actual; ?>)</h2>
<div class="card">
  <?php if (count($asistencias_clientes) > 0): ?>
  <table>
    <tr>
      <th>Apellido</th>
      <th>Nombre</th>
      <th>Fecha</th>
      <th>Hora</th>
    </tr>
    <?php foreach ($asistencias_clientes as $asistencia): ?>
    <tr>
      <td><?php echo $asistencia['apellido']; ?></td>
      <td><?php echo $asistencia['nombre']; ?></td>
      <td><?php echo $asistencia['fecha']; ?></td>
      <td><?php echo $asistencia['hora']; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
    <p>No hay asistencias de clientes hoy.</p>
  <?php endif; ?>
</div>

<h2>Asistencias de Profesores - Hoy (<?php echo $fecha_actual; ?>)</h2>
<div class="card">
  <?php if (count($asistencias_profesores) > 0): ?>
  <table>
    <tr>
      <th>Apellido</th>
      <th>Nombre</th>
      <th>Fecha y Hora</th>
      <th>Tipo</th>
    </tr>
    <?php foreach ($asistencias_profesores as $asistencia): ?>
    <tr>
      <td><?php echo $asistencia['apellido']; ?></td>
      <td><?php echo $asistencia['nombre']; ?></td>
      <td><?php echo $asistencia['fecha_hora']; ?></td>
      <td><?php echo ucfirst($asistencia['tipo']); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
    <p>No hay asistencias de profesores hoy.</p>
  <?php endif; ?>
</div>

</body>
</html>
