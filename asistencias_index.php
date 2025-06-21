<?php
include 'conexion.php';

$hoy = date("Y-m-d");

// Clientes con asistencia hoy
$asistencias_clientes = $conexion->query("SELECT c.nombre, c.apellido, a.fecha, a.hora 
    FROM asistencias_clientes a 
    JOIN clientes c ON a.cliente_id = c.id 
    WHERE a.fecha = '$hoy'");

// Profesores con asistencia hoy
$asistencias_profesores = $conexion->query("SELECT p.nombre, p.apellido, a.hora_ingreso, a.hora_egreso 
    FROM asistencias_profesores a 
    JOIN profesores p ON a.profesor_id = p.id 
    WHERE a.fecha = '$hoy'");
?>

<div style='display: flex; flex-wrap: wrap; justify-content: space-around; margin-top: 30px; color: gold;'>
  <div style='flex: 1; min-width: 300px; background-color: #111; padding: 20px; margin: 10px; border: 1px solid gold; border-radius: 10px;'>
    <h2>📋 Asistencias de Clientes (Hoy)</h2>
    <table style='width: 100%; color: white;'>
      <tr><th>Nombre</th><th>Apellido</th><th>Hora</th></tr>
      <?php while ($c = $asistencias_clientes->fetch_assoc()) {
        echo "<tr><td>{$c['nombre']}</td><td>{$c['apellido']}</td><td>{$c['hora']}</td></tr>";
      } ?>
    </table>
  </div>

  <div style='flex: 1; min-width: 300px; background-color: #111; padding: 20px; margin: 10px; border: 1px solid gold; border-radius: 10px;'>
    <h2>🧑‍🏫 Asistencias de Profesores (Hoy)</h2>
    <table style='width: 100%; color: white;'>
      <tr><th>Nombre</th><th>Apellido</th><th>Ingreso</th><th>Egreso</th></tr>
      <?php while ($p = $asistencias_profesores->fetch_assoc()) {
        echo "<tr><td>{$p['nombre']}</td><td>{$p['apellido']}</td><td>{$p['hora_ingreso']}</td><td>{$p['hora_egreso']}</td></tr>";
      } ?>
    </table>
  </div>
</div>
