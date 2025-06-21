<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$nombre_gimnasio = $_SESSION['nombre_gimnasio'] ?? 'Academy';

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $fecha = date('Y-m-d');
    $sql = "SELECT c.apellido, c.nombre, a.fecha, a.hora 
            FROM asistencias_clientes a 
            INNER JOIN clientes c ON a.cliente_id = c.id 
            WHERE c.gimnasio_id = $gimnasio_id AND a.fecha = '$fecha'
            ORDER BY a.hora DESC";
    return $conexion->query($sql);
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    $fecha = date('Y-m-d');
    $sql = "SELECT p.apellido, p.nombre, a.hora_ingreso, a.hora_salida 
            FROM asistencias_profesores a 
            INNER JOIN profesores p ON a.profesor_id = p.id 
            WHERE p.gimnasio_id = $gimnasio_id AND a.fecha = '$fecha'
            ORDER BY a.hora_ingreso DESC";
    return $conexion->query($sql);
}

function obtenerCumpleaños($conexion, $gimnasio_id) {
    $mes = date('m');
    $dia = date('d');
    $sql = "SELECT nombre, apellido, fecha_nacimiento 
            FROM clientes 
            WHERE MONTH(fecha_nacimiento) = $mes AND DAY(fecha_nacimiento) >= $dia
            AND gimnasio_id = $gimnasio_id
            ORDER BY fecha_nacimiento ASC LIMIT 5";
    return $conexion->query($sql);
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $limite = date('Y-m-d', strtotime('+10 days'));
    $sql = "SELECT c.nombre, c.apellido, m.fecha_vencimiento 
            FROM membresias m 
            INNER JOIN clientes c ON m.cliente_id = c.id 
            WHERE m.fecha_vencimiento BETWEEN '$hoy' AND '$limite'
            AND c.gimnasio_id = $gimnasio_id
            ORDER BY m.fecha_vencimiento ASC LIMIT 5";
    return $conexion->query($sql);
}

$clientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$profesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id);
$cumples = obtenerCumpleaños($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel de Control</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 60px 15px;
    }
    h1, h2 {
      text-align: center;
      margin-bottom: 10px;
    }
    .section {
      margin-bottom: 30px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      font-size: 14px;
    }
    th, td {
      border: 1px solid gold;
      padding: 6px 8px;
      text-align: center;
    }
    th {
      background-color: #222;
    }
    @media screen and (max-width: 600px) {
      body {
        font-size: 13px;
        padding: 15px;
      }
      th, td {
        padding: 4px;
      }
    }
    .icon {
      margin-right: 5px;
    }
  </style>
</head>
<body>

<h1><i class="fas fa-dumbbell icon"></i> Fight Academy - <?= strtoupper($nombre_gimnasio) ?></h1>
<h2><i class="fas fa-chart-line icon"></i> Panel de Control</h2>

<div class="section">
  <h3><i class="fas fa-user-check icon"></i> Asistencias de Clientes - <?= date("Y-m-d") ?></h3>
  <table>
    <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>
    <?php while($fila = $clientes->fetch_assoc()): ?>
      <tr>
        <td><?= $fila['apellido'] ?></td>
        <td><?= $fila['nombre'] ?></td>
        <td><?= $fila['fecha'] ?></td>
        <td><?= $fila['hora'] ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<div class="section">
  <h3><i class="fas fa-chalkboard-teacher icon"></i> Asistencias de Profesores - <?= date("Y-m-d") ?></h3>
  <table>
    <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Salida</th></tr>
    <?php while($fila = $profesores->fetch_assoc()): ?>
      <tr>
        <td><?= $fila['apellido'] ?></td>
        <td><?= $fila['nombre'] ?></td>
        <td><?= $fila['hora_ingreso'] ?></td>
        <td><?= $fila['hora_salida'] ?? '-' ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<div class="section">
  <h3><i class="fas fa-birthday-cake icon"></i> Próximos Cumpleaños</h3>
  <table>
    <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th></tr>
    <?php while($fila = $cumples->fetch_assoc()): ?>
      <tr>
        <td><?= $fila['apellido'] ?></td>
        <td><?= $fila['nombre'] ?></td>
        <td><?= date("d-m", strtotime($fila['fecha_nacimiento'])) ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<div class="section">
  <h3><i class="fas fa-calendar-times icon"></i> Vencimientos Próximos</h3>
  <table>
    <tr><th>Apellido</th><th>Nombre</th><th>Vencimiento</th></tr>
    <?php while($fila = $vencimientos->fetch_assoc()): ?>
      <tr>
        <td><?= $fila['apellido'] ?></td>
        <td><?= $fila['nombre'] ?></td>
        <td><?= date("d-m-Y", strtotime($fila['fecha_vencimiento'])) ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

</body>
</html>
