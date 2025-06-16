<?php
include 'conexion.php';
include 'menu.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Clientes registrados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background-color: #111;
      color: #f5c518;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }
    .container {
      padding: 20px;
      overflow-x: auto;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #222;
      color: #fff;
    }
    th, td {
      border: 1px solid #333;
      padding: 10px;
      text-align: left;
    }
    th {
      background-color: #000;
      color: gold;
    }
    tr:nth-child(even) {
      background-color: #1a1a1a;
    }
    .btn {
      padding: 5px 10px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      color: #fff;
    }
    .btn-edit {
      background-color: #28a745;
    }
    .btn-delete {
      background-color: #dc3545;
    }
    .actions {
      display: flex;
      gap: 5px;
    }
    @media (max-width: 600px) {
      th, td {
        font-size: 14px;
        padding: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Clientes registrados</h2>
    <table>
      <thead>
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
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sql = "SELECT * FROM clientes";
        $resultado = $conexion->query($sql);

        while ($fila = $resultado->fetch_assoc()) {
          echo "<tr>";
          echo "<td>" . htmlspecialchars($fila['apellido'] ?? '') . "</td>";
          echo "<td>" . htmlspecialchars($fila['nombre'] ?? '') . "</td>";
          echo "<td>" . htmlspecialchars($fila['dni'] ?? '') . "</td>";
          echo "<td>" . htmlspecialchars($fila['fecha_nacimiento'] ?? '') . "</td>";
          echo "<td>" . htmlspecialchars($fila['edad'] ?? '') . "</td>";
          echo "<td>" . htmlspecialchars($fila['domicilio'] ?? '') . "</td>";
          echo "<td>" . htmlspecialchars($fila['telefono'] ?? '') . "</td>";
          echo "<td>" . htmlspecialchars($fila['email'] ?? '') . "</td>";
          echo "<td>" . htmlspecialchars($fila['rfid'] ?? '') . "</td>";
          echo "<td>" . htmlspecialchars($fila['gimnasio'] ?? '') . "</td>";
          echo "<td class='actions'>
                  <a href='editar_cliente.php?id={$fila['id']}' class='btn btn-edit'>Editar</a>
                  <a href='eliminar_cliente.php?id={$fila['id']}' class='btn btn-delete' onclick=\"return confirm('¿Deseas eliminar este cliente?')\">Eliminar</a>
                </td>";
          echo "</tr>";
        }
        ?>
      </tbody>
    </table>
  </div>
</body>
</html>
