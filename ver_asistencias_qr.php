<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

date_default_timezone_set('America/Argentina/Buenos_Aires');

// CONSULTA asistencias del mes actual
$consulta = $conexion->prepare("
SELECT 
    a.fecha, 
    a.hora, 
    c.apellido, 
    c.nombre, 
    c.disciplina, 
    m.clases_disponibles, 
    m.fecha_vencimiento
FROM asistencias a
INNER JOIN clientes c ON a.cliente_id = c.id
INNER JOIN membresias m ON m.cliente_id = c.id
WHERE MONTH(a.fecha) = MONTH(CURDATE())
  AND YEAR(a.fecha) = YEAR(CURDATE())
  AND c.gimnasio_id = ?
ORDER BY a.fecha DESC, a.hora DESC
");

$consulta->bind_param("i", $gimnasio_id);
$consulta->execute();
$resultado = $consulta->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Asistencias del Mes</title>
  <style>
    body {
      background-color: black;
      color: gold;
      font-family: Arial, sans-serif;
      text-align: center;
    }
    h1 {
      margin-top: 20px;
      font-size: 28px;
    }
    table {
      margin: 30px auto;
      border-collapse: collapse;
      width: 90%;
    }
    th, td {
      border: 1px solid gold;
      padding: 10px;
    }
    th {
      background-color: #222;
    }
  </style>
</head>
<body>
  <h1>Asistencias del Mes</h1>
  <table>
    <tr>
      <th>Fecha</th>
      <th>Hora</th>
      <th>Apellido</th>
      <th>Nombre</th>
      <th>Disciplina</th>
      <th>Clases Restantes</th>
      <th>Vencimiento</th>
    </tr>
    <?php
    if ($resultado->num_rows > 0) {
        while ($fila = $resultado->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $fila['fecha'] . "</td>";
            echo "<td>" . $fila['hora'] . "</td>";
            echo "<td>" . $fila['apellido'] . "</td>";
            echo "<td>" . $fila['nombre'] . "</td>";
            echo "<td>" . ($fila['disciplina'] ?? 'Sin asignar') . "</td>";
            echo "<td>" . $fila['clases_disponibles'] . "</td>";
            echo "<td>" . $fila['fecha_vencimiento'] . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7'>No hay asistencias registradas este mes.</td></tr>";
    }
    ?>
  </table>
</body>
</html>
