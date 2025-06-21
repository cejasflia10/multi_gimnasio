
<?php
include 'conexion.php';
date_default_timezone_set("America/Argentina/Buenos_Aires");
$fecha_actual = date("Y-m-d");
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
?>

<div style="display: flex; gap: 40px; flex-wrap: wrap; margin-top: 30px;">

  <!-- CLIENTES -->
  <div style='flex: 1; min-width: 300px;'>
    <h3 style='color: #FFD700;'>📋 Clientes</h3>
    <table style='width:100%; background: #222; color: white; border-collapse: collapse;'>
      <tr><th>Nombre</th><th>Hora</th><th>Clases</th></tr>
      <?php
      $query = "
        SELECT c.nombre, c.apellido, a.hora, m.clases_disponibles
        FROM asistencias a
        INNER JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN membresias m ON m.cliente_id = c.id
        WHERE a.fecha = '$fecha_actual' AND a.id_gimnasio = $gimnasio_id
        ORDER BY a.hora DESC
      ";
      $res = $conexion->query($query);
      while($row = $res->fetch_assoc()) {
        echo "<tr style='text-align:center;'><td>{$row['nombre']} {$row['apellido']}</td><td>{$row['hora']}</td><td>{$row['clases_disponibles']}</td></tr>";
      }
      ?>
    </table>
  </div>

  <!-- PROFESORES -->
  <div style='flex: 1; min-width: 300px;'>
    <h3 style='color: #FFD700;'>👨‍🏫 Profesores</h3>
    <table style='width:100%; background: #222; color: white; border-collapse: collapse;'>
      <tr><th>Profesor</th><th>Ingreso</th><th>Egreso</th></tr>
      <?php
      $query = "
        SELECT p.apellido, r.ingreso, r.egreso
        FROM rfid_profesores_registros r
        INNER JOIN profesores p ON r.profesor_id = p.id
        WHERE r.fecha = '$fecha_actual' AND r.gimnasio_id = $gimnasio_id
        ORDER BY r.ingreso DESC
      ";
      $res = $conexion->query($query);
      while($row = $res->fetch_assoc()) {
        echo "<tr style='text-align:center;'><td>{$row['apellido']}</td><td>{$row['ingreso']}</td><td>{$row['egreso']}</td></tr>";
      }
      ?>
    </table>
  </div>

</div>
