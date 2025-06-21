<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'menu.php';
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date("Y-m-d");

// FUNCIONES DE CONSULTA
function obtenerAsistenciasClientes($conexion, $gimnasio_id, $fecha) {
    $sql = "SELECT c.apellido, c.nombre, a.fecha, a.hora
            FROM asistencias_clientes a
            JOIN clientes c ON a.cliente_id = c.id
            WHERE c.gimnasio_id = $gimnasio_id AND a.fecha = '$fecha'
            ORDER BY a.fecha DESC, a.hora DESC
            LIMIT 5";
    return $conexion->query($sql);
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id, $fecha) {
    $sql = "SELECT p.apellido, p.nombre, r.ingreso, r.salida
            FROM registro_profesores r
            JOIN profesores p ON r.profesor_id = p.id
            WHERE p.gimnasio_id = $gimnasio_id AND DATE(r.ingreso) = '$fecha'
            ORDER BY r.ingreso DESC
            LIMIT 5";
    return $conexion->query($sql);
}

function obtenerCumpleaniosProximos($conexion, $gimnasio_id) {
    $sql = "SELECT apellido, nombre, fecha_nacimiento 
            FROM clientes 
            WHERE gimnasio_id = $gimnasio_id 
              AND MONTH(fecha_nacimiento) = MONTH(CURDATE()) 
              AND DAY(fecha_nacimiento) >= DAY(CURDATE())
            ORDER BY fecha_nacimiento ASC LIMIT 5";
    return $conexion->query($sql);
}

function obtenerVencimientosProximos($conexion, $gimnasio_id) {
    $sql = "SELECT c.apellido, c.nombre, m.fecha_vencimiento 
            FROM membresias m 
            JOIN clientes c ON m.cliente_id = c.id
            WHERE c.gimnasio_id = $gimnasio_id AND m.fecha_vencimiento >= CURDATE()
            ORDER BY m.fecha_vencimiento ASC LIMIT 5";
    return $conexion->query($sql);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel - Fight Academy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: #111;
      color: #FFD700;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 0;
    }
    .container {
      margin-left: 250px;
      padding: 20px;
    }
    h2 {
      margin-top: 30px;
      color: #FFD700;
      font-size: 24px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #222;
      color: #fff;
      margin-top: 10px;
      font-size: 16px;
    }
    th, td {
      border: 1px solid #FFD700;
      padding: 8px;
      text-align: center;
    }
    th {
      background-color: #333;
      color: #FFD700;
    }
    @media (max-width: 768px) {
      .container {
        margin-left: 0;
        padding: 10px;
      }
      table, thead, tbody, th, td, tr {
        font-size: 14px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>📅 Asistencias de Clientes - <?= $hoy ?></h2>
    <table>
      <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>
      <?php
      $clientes = obtenerAsistenciasClientes($conexion, $gimnasio_id, $hoy);
      while ($row = $clientes->fetch_assoc()) {
          echo "<tr><td>{$row['apellido']}</td><td>{$row['nombre']}</td><td>{$row['fecha']}</td><td>{$row['hora']}</td></tr>";
      }
      ?>
    </table>

    <h2>👨‍🏫 Asistencias de Profesores</h2>
    <table>
      <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Salida</th></tr>
      <?php
      $profesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id, $hoy);
      while ($row = $profesores->fetch_assoc()) {
          echo "<tr><td>{$row['apellido']}</td><td>{$row['nombre']}</td><td>{$row['ingreso']}</td><td>{$row['salida']}</td></tr>";
      }
      ?>
    </table>

    <h2>🎂 Próximos Cumpleaños</h2>
    <table>
      <tr><th>Apellido</th><th>Nombre</th><th>Fecha Nacimiento</th></tr>
      <?php
      $cumples = obtenerCumpleaniosProximos($conexion, $gimnasio_id);
      while ($row = $cumples->fetch_assoc()) {
          echo "<tr><td>{$row['apellido']}</td><td>{$row['nombre']}</td><td>{$row['fecha_nacimiento']}</td></tr>";
      }
      ?>
    </table>

    <h2>⏳ Próximos Vencimientos</h2>
    <table>
      <tr><th>Apellido</th><th>Nombre</th><th>Fecha Vencimiento</th></tr>
      <?php
      $vencimientos = obtenerVencimientosProximos($conexion, $gimnasio_id);
      while ($row = $vencimientos->fetch_assoc()) {
          echo "<tr><td>{$row['apellido']}</td><td>{$row['nombre']}</td><td>{$row['fecha_vencimiento']}</td></tr>";
      }
      ?>
    </table>
  </div>
</body>
</html>
