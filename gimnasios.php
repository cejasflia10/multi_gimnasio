<?php
include 'conexion.php';
include 'menu.php';
$resultado = $conexion->query("SELECT * FROM gimnasios");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listado de Gimnasios</title>
  <style>
    body { background-color: #111; color: #f1f1f1; font-family: Arial, sans-serif; padding: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; background-color: #222; }
    th, td { padding: 10px; border: 1px solid #444; text-align: left; }
    th { background-color: #333; color: gold; }
    a.boton-agregar { background: gold; padding: 10px; color: black; text-decoration: none; border-radius: 5px; font-weight: bold; }
    .acciones a { margin-right: 10px; color: gold; text-decoration: none; }
  </style>
</head>
<body>
  <h2>Listado de Gimnasios</h2>
  <a href="agregar_gimnasio.php" class="boton-agregar">➕ Agregar Gimnasio</a>
  <table>
    <tr>
      <th>Nombre</th><th>Dirección</th><th>Teléfono</th><th>Email</th><th>Acciones</th>
    </tr>
    <?php while ($row = $resultado->fetch_assoc()) { ?>
      <tr>
        <td><?php echo $row["nombre"]; ?></td>
        <td><?php echo $row["direccion"]; ?></td>
        <td><?php echo $row["telefono"]; ?></td>
        <td><?php echo $row["email"]; ?></td>
        <td class="acciones">
          <a href='editar_gimnasio.php?id=<?php echo $row["id"]; ?>'>✏️ Editar</a>
          <a href='eliminar_gimnasio.php?id=<?php echo $row["id"]; ?>' onclick="return confirm('¿Eliminar este gimnasio?')">🗑️ Eliminar</a>
        </td>
      </tr>
    <?php } ?>
  </table>
</body>
</html>
