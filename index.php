<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'menu.php';
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date("Y-m-d");

// Obtener asistencias de clientes
function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $sql = "SELECT c.apellido, c.nombre, a.fecha, a.hora 
            FROM asistencias_clientes a
            INNER JOIN clientes c ON c.id = a.cliente_id
            WHERE c.gimnasio_id = $gimnasio_id AND a.fecha = CURDATE()
            ORDER BY a.fecha_hora DESC
            LIMIT 10";
    return $conexion->query($sql);
}

// Obtener asistencias de profesores
function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    $sql = "SELECT p.apellido, p.nombre, r.fecha, r.hora_ingreso, r.hora_salida 
            FROM registro_profesores r
            INNER JOIN profesores p ON p.id = r.profesor_id
            WHERE p.gimnasio_id = $gimnasio_id AND r.fecha = CURDATE()
            ORDER BY r.fecha_hora DESC
            LIMIT 10";
    return $conexion->query($sql);
}

$asistenciasClientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$asistenciasProfesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Panel - Gym</title>
  <style>
    body {
      background-color: #111;
      color: #fff;
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
    }
    .contenido {
      margin-left: 250px;
      padding: 20px;
    }
    .panel {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 15px;
    }
    .card {
      background-color: #222;
      border: 1px solid #555;
      border-radius: 10px;
      padding: 15px;
      text-align: center;
    }
    h3 {
      margin-top: 0;
      color: gold;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      padding: 6px;
      border: 1px solid #444;
      text-align: center;
      font-size: 14px;
    }
    th {
      background-color: #333;
      color: gold;
    }

    @media (max-width: 768px) {
      .contenido {
        margin-left: 0;
        padding: 10px;
      }
    }
  </style>
</head>
<body>

<div class="contenido">
  <h2>Panel de Control - <?php echo $_SESSION['nombre_gimnasio'] ?? 'Gimnasio'; ?></h2>

  <div class="panel">
    <div class="card">
      <h3>Asistencias de Clientes</h3>
      <table>
        <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>
        <?php while ($row = $asistenciasClientes->fetch_assoc()): ?>
          <tr>
            <td><?= $row['apellido'] ?></td>
            <td><?= $row['nombre'] ?></td>
            <td><?= $row['fecha'] ?></td>
            <td><?= $row['hora'] ?></td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>

    <div class="card">
      <h3>Asistencias de Profesores</h3>
      <table>
        <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Salida</th></tr>
        <?php while ($row = $asistenciasProfesores->fetch_assoc()): ?>
          <tr>
            <td><?= $row['apellido'] ?></td>
            <td><?= $row['nombre'] ?></td>
            <td><?= $row['hora_ingreso'] ?></td>
            <td><?= $row['hora_salida'] ?></td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>

    <!-- Agregar aquí más paneles como pagos del día, ventas, cumpleaños, etc. -->

  </div>
</div>

</body>
</html>
