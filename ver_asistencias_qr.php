<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["gimnasio_id"])) {
    die("Acceso denegado.");
}
include "conexion.php";
$gimnasio_id = $_SESSION["gimnasio_id"];
$sql = "SELECT c.apellido, c.nombre, c.dni, a.fecha_hora, c.clases_restantes, d.nombre AS disciplina
        FROM asistencias a
        JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN disciplinas d ON c.disciplina = d.id
        WHERE c.gimnasio_id = $gimnasio_id
        ORDER BY a.fecha_hora DESC";
$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Asistencias QR - Fight Academy</title>
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h1 {
      text-align: center;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      padding: 10px;
      border: 1px solid gold;
      text-align: center;
    }
    .btn-volver {
      display: block;
      width: 200px;
      margin: 20px auto;
      padding: 10px;
      text-align: center;
      background-color: gold;
      color: black;
      text-decoration: none;
      border-radius: 8px;
    }
  </style>
</head>
<body>
  <h1>Asistencias QR - <?php echo $_SESSION["nombre_gimnasio"] ?? ''; ?></h1>
  <table>
    <thead>
      <tr>
        <th>Cliente</th>
        <th>DNI</th>
        <th>Fecha y Hora</th>
        <th>Clases Restantes</th>
        <th>Disciplina</th>
      </tr>
    </thead>
    <tbody>
      <?php while($fila = $resultado->fetch_assoc()): ?>
        <tr>
          <td><?= $fila["apellido"] ?>, <?= $fila["nombre"] ?></td>
          <td><?= $fila["dni"] ?></td>
          <td><?= $fila["fecha_hora"] ?></td>
          <td><?= $fila["clases_restantes"] ?></td>
          <td><?= $fila["disciplina"] ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  <a href="index.php" class="btn-volver">Volver al Panel</a>
</body>
</html>
