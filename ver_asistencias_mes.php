<?php
session_start();
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

$condicion = ($rol === 'admin') ? '1' : "a.id_gimnasio = $gimnasio_id";

$sql = "
SELECT a.fecha, a.hora, c.apellido, c.nombre, c.disciplina, m.clases_restantes
FROM asistencias a
JOIN clientes c ON a.cliente_id = c.id
LEFT JOIN membresias m ON m.cliente_id = c.id
WHERE $condicion
AND DATE_FORMAT(a.fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
ORDER BY a.fecha DESC, a.hora DESC";

$res = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Asistencias del Mes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 1rem;
    }
    h2 {
      color: gold;
      text-align: center;
      margin-bottom: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
    }
    th, td {
      border: 1px solid gold;
      padding: 10px;
      text-align: center;
    }
    th {
      background-color: #222;
    }
    tr:nth-child(even) {
      background-color: #1a1a1a;
    }
    tr:hover {
      background-color: #333;
    }
    @media (max-width: 768px) {
      table, thead, tbody, th, td, tr {
        font-size: 14px;
      }
    }
  </style>
</head>
<body>

<h2>📆 Asistencias del Mes</h2>

<table>
  <thead>
    <tr>
      <th>Fecha</th>
      <th>Hora</th>
      <th>Apellido</th>
      <th>Nombre</th>
      <th>Disciplina</th>
      <th>Clases Restantes</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $res->fetch_assoc()) { ?>
      <tr>
        <td><?= $row['fecha'] ?></td>
        <td><?= $row['hora'] ?></td>
        <td><?= $row['apellido'] ?></td>
        <td><?= $row['nombre'] ?></td>
        <td><?= $row['disciplina'] ?></td>
        <td><?= $row['clases_restantes'] ?? '0' ?></td>
      </tr>
    <?php } ?>
  </tbody>
</table>

</body>
</html>
