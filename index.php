<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date('Y-m-d');

// Obtener asistencias de clientes
function obtenerAsistenciasClientes($conexion, $gimnasio_id, $fecha) {
    $sql = "SELECT c.apellido, c.nombre, a.fecha, a.hora
            FROM asistencias_clientes a
            INNER JOIN clientes c ON c.id = a.cliente_id
            WHERE c.gimnasio_id = $gimnasio_id AND a.fecha = '$fecha'
            ORDER BY a.fecha_hora DESC";
    return $conexion->query($sql);
}

// Obtener asistencias de profesores
function obtenerAsistenciasProfesores($conexion, $gimnasio_id, $fecha) {
    $sql = "SELECT p.apellido, p.nombre, a.fecha, a.hora_ingreso, a.hora_salida
            FROM asistencias_profesores a
            INNER JOIN profesores p ON p.id = a.profesor_id
            WHERE a.gimnasio_id = $gimnasio_id AND a.fecha = '$fecha'
            ORDER BY a.fecha DESC";
    return $conexion->query($sql);
}

// Próximos cumpleaños
function obtenerCumpleanios($conexion, $gimnasio_id) {
    $mes = date('m');
    $dia = date('d');
    $sql = "SELECT apellido, nombre, fecha_nacimiento
            FROM clientes
            WHERE MONTH(fecha_nacimiento) = $mes AND DAY(fecha_nacimiento) >= $dia AND gimnasio_id = $gimnasio_id
            ORDER BY DAY(fecha_nacimiento)";
    return $conexion->query($sql);
}

// Próximos vencimientos
function obtenerVencimientos($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $sql = "SELECT c.apellido, c.nombre, m.fecha_vencimiento
            FROM membresias m
            INNER JOIN clientes c ON c.id = m.cliente_id
            WHERE m.fecha_vencimiento >= '$hoy' AND c.gimnasio_id = $gimnasio_id
            ORDER BY m.fecha_vencimiento ASC
            LIMIT 10";
    return $conexion->query($sql);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control - Fight Academy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background-color: #111;
      color: #f1c40f;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0 10px 40px 240px;
    }
    h2 {
      margin-top: 40px;
      font-size: 22px;
      color: #f1c40f;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
    }
    th, td {
      border: 1px solid #f1c40f;
      padding: 8px;
      text-align: center;
    }
    th {
      background-color: #222;
    }
    @media screen and (max-width: 768px) {
      body {
        padding: 60px 10px 40px 10px;
      }
      table, th, td {
        font-size: 14px;
      }
    }
  </style>
</head>
<body>
  <h1>🏋️‍♂️ Fight Academy - ACADEMY</h1>
  <h2>📋 Panel de Control</h2>

  <h2>👥 Asistencias de Clientes - <?= $hoy ?></h2>
  <?php
    $asistenciasClientes = obtenerAsistenciasClientes($conexion, $gimnasio_id, $hoy);
    if ($asistenciasClientes->num_rows > 0) {
        echo "<table><tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>";
        while ($row = $asistenciasClientes->fetch_assoc()) {
            echo "<tr><td>{$row['apellido']}</td><td>{$row['nombre']}</td><td>{$row['fecha']}</td><td>{$row['hora']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:white;'>No se registraron asistencias de clientes hoy.</p>";
    }
  ?>

  <h2>🧑‍🏫 Asistencias de Profesores - <?= $hoy ?></h2>
  <?php
    $asistenciasProfesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id, $hoy);
    if ($asistenciasProfesores->num_rows > 0) {
        echo "<table><tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Salida</th></tr>";
        while ($row = $asistenciasProfesores->fetch_assoc()) {
            echo "<tr><td>{$row['apellido']}</td><td>{$row['nombre']}</td><td>{$row['hora_ingreso']}</td><td>{$row['hora_salida']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:white;'>No se registraron asistencias de profesores hoy.</p>";
    }
  ?>

  <h2>🎂 Próximos Cumpleaños</h2>
  <?php
    $cumpleanios = obtenerCumpleanios($conexion, $gimnasio_id);
    if ($cumpleanios->num_rows > 0) {
        echo "<table><tr><th>Apellido</th><th>Nombre</th><th>Fecha</th></tr>";
        while ($row = $cumpleanios->fetch_assoc()) {
            echo "<tr><td>{$row['apellido']}</td><td>{$row['nombre']}</td><td>{$row['fecha_nacimiento']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:white;'>No hay cumpleaños próximos.</p>";
    }
  ?>

  <h2>⏰ Vencimientos Próximos</h2>
  <?php
    $vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
    if ($vencimientos->num_rows > 0) {
        echo "<table><tr><th>Apellido</th><th>Nombre</th><th>Vencimiento</th></tr>";
        while ($row = $vencimientos->fetch_assoc()) {
            echo "<tr><td>{$row['apellido']}</td><td>{$row['nombre']}</td><td>{$row['fecha_vencimiento']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:white;'>No hay vencimientos próximos.</p>";
    }
  ?>
</body>
</html>
