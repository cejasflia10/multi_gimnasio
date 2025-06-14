<?php
include 'menu.php';
include 'conexion.php';

$resultado = $conexion->query("SELECT * FROM usuarios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
    }

    .container {
      max-width: 900px;
      margin: 50px auto;
      padding: 20px;
    }

    h2 {
      text-align: center;
      color: #ffc107;
      margin-bottom: 30px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #222;
      text-align: center;
    }

    th, td {
      border: 1px solid #333;
      padding: 10px;
    }

    th {
      background-color: #ffc107;
      color: #111;
    }

    .btn {
      padding: 5px 10px;
      border: none;
      border-radius: 4px;
      text-decoration: none;
      margin: 2px;
    }

    .btn-warning {
      background-color: #ffc107;
      color: #111;
    }

    .btn-danger {
      background-color: #dc3545;
      color: white;
    }
  </style>
</head>
<body>

<div class="container">
  <h2>Usuarios Registrados</h2>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Usuario</th>
        <th>Contraseña</th>
        <th>ID Gimnasio</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($fila = $resultado->fetch_assoc()) { ?>
        <tr>
          <td><?= $fila['id'] ?></td>
          <td><?= $fila['nombre_usuario'] ?></td>
          <td><?= str_repeat('*', strlen($fila['contrasena'])) ?></td>
          <td><?= $fila['id_gimnasio'] ?></td>
          <td>
            <a href="editar_usuario.php?id=<?= $fila['id'] ?>" class="btn btn-warning">Editar</a>
            <a href="eliminar_usuario.php?id=<?= $fila['id'] ?>" class="btn btn-danger">Eliminar</a>
          </td>
        </tr>
      <?php } ?>
    </tbody>
  </table>
</div>

</body>
</html>
