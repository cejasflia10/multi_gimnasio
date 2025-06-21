<?php
include 'conexion.php';

// Consulta con JOIN para mostrar el nombre del gimnasio
$consulta = "SELECT usuarios.*, gimnasios.nombre AS nombre_gimnasio 
             FROM usuarios 
             LEFT JOIN gimnasios ON usuarios.id_gimnasio = gimnasios.id";
$resultado = $conexion->query($consulta);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios - Multi Gimnasio</title>
  <style>
    body {
      background-color: #111;
      color: #fff;
      font-family: Arial;
      padding: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #222;
    }
    th, td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #444;
    }
    th {
      background-color: gold;
      color: #000;
    }
    a {
      color: gold;
      text-decoration: none;
    }
    .btn-agregar {
      background-color: gold;
      color: #000;
      padding: 10px 20px;
      text-decoration: none;
      border-radius: 6px;
      display: inline-block;
      margin-bottom: 20px;
      font-weight: bold;
    }
    .btn-agregar:hover {
      background-color: #ffd700;
    }
  </style>
</head>
<body>

<h2>Listado de Usuarios</h2>

<a href="agregar_usuario.php" class="btn-agregar">➕ Agregar Usuario</a>

<table>
  <thead>
    <tr>
      <th>Nombre de Usuario</th>
      <th>Rol</th>
      <th>Gimnasio</th>
      <th>Acciones</th>
    <th>Rol</th><th>Clientes</th><th>Membresías</th><th>Profesores</th><th>Ventas</th><th>Asistencias</th></tr>
  </thead>
  <tbody>
    <?php while ($usuario = $resultado->fetch_assoc()): ?>
      <tr>
        <td><?= $usuario['usuario'] ?></td>
        <td><?= $usuario['rol'] ?></td>
        <td><?= $usuario['nombre_gimnasio'] ?? 'Sin asignar' ?></td>
        <td>
          <a href="editar_usuario.php?id=<?= $usuario['id'] ?>">✏️ Editar</a> |
          <a href="eliminar_usuario.php?id=<?= $usuario['id'] ?>" onclick="return confirm('¿Eliminar este usuario?')">🗑️ Eliminar</a>
        </td>
      <th>Rol</th><th>Clientes</th><th>Membresías</th><th>Profesores</th><th>Ventas</th><th>Asistencias</th></tr>
    <?php endwhile; ?>
  </tbody>
</table>

</body>
</html>
