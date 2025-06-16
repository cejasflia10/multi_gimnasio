
<?php
include 'conexion.php';
include 'menu.php';

$consulta = "SELECT * FROM clientes";
$resultado = $conexion->query($consulta);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Clientes Registrados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h1 {
      text-align: center;
      font-size: 24px;
      margin-bottom: 20px;
    }
    .tabla-container {
      overflow-x: auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #222;
      color: #fff;
    }
    th, td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #444;
    }
    th {
      background-color: #333;
      color: gold;
    }
    .btn-agregar {
      background-color: gold;
      color: #000;
      padding: 10px 15px;
      text-decoration: none;
      font-weight: bold;
      display: inline-block;
      margin-bottom: 10px;
      border-radius: 5px;
    }
  </style>
</head>
<body>

<h1>Clientes registrados</h1>
<a class="btn-agregar" href="agregar_cliente.php">+ Agregar Cliente</a>

<div class="tabla-container">
<table>
  <tr>
    <th>Apellido</th>
    <th>Nombre</th>
    <th>DNI</th>
    <th>Fecha Nac.</th>
    <th>Edad</th>
    <th>Domicilio</th>
    <th>Teléfono</th>
    <th>Email</th>
    <th>RFID</th>
    <th>Gimnasio</th>
  </tr>
  <?php while ($fila = $resultado->fetch_assoc()) { ?>
    <tr>
      <td><?php echo htmlspecialchars($fila['apellido']); ?></td>
      <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
      <td><?php echo htmlspecialchars($fila['dni']); ?></td>
      <td><?php echo htmlspecialchars($fila['fecha_nacimiento']); ?></td>
      <td><?php echo htmlspecialchars($fila['edad']); ?></td>
      <td><?php echo htmlspecialchars($fila['domicilio']); ?></td>
      <td><?php echo htmlspecialchars($fila['telefono']); ?></td>
      <td><?php echo htmlspecialchars($fila['email']); ?></td>
      <td><?php echo htmlspecialchars($fila['rfid']); ?></td>
      <td><?php echo htmlspecialchars($fila['gimnasio']); ?></td>
    </tr>
  <?php } ?>
</table>
</div>

</body>
</html>
