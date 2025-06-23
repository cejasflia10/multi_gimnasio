<?php
include 'conexion.php';

$resultado = $conexion->query("SELECT * FROM usuarios");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios Registrados</title>
  <style>
    body { background-color: #111; color: #fff; font-family: Arial; margin: 0; padding: 20px; }
    h2 { color: gold; text-align: center; }
    table { width: 90%; margin: 20px auto; border-collapse: collapse; background: #222; color: #f1f1f1; }
    th, td { padding: 10px; border: 1px solid #444; text-align: center; }
    th { background-color: #333; color: gold; }
    .btn { padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
    .editar { background-color: #ffc107; color: #000; }
    .eliminar { background-color: #dc3545; color: #fff; }
  </style>
</head>
<body>
  <h2>Usuarios Registrados</h2>
  <table>
    <tr>
      <th>Usuario</th>
      <th>Contraseña</th>
      <th>ID Gimnasio</th>
      <th>Acciones</th>
    </tr>
    <?php while ($row = $resultado->fetch_assoc()) { ?>
    <tr>
      <td><?php echo htmlspecialchars($row['usuario']); ?></td>
      <td>********</td>
      <td><?php echo $row['id_gimnasio']; ?></td>
      <td>
        <a href="editar_usuario.php?id=<?php echo $row['id']; ?>" class="btn editar">Editar</a>
        <a href="eliminar_usuario.php?id=<?php echo $row['id']; ?>" class="btn eliminar" onclick="return confirm('¿Eliminar este usuario?')">Eliminar</a>
      </td>
    </tr>
    <?php } ?>
  </table>
</body>
</html>
