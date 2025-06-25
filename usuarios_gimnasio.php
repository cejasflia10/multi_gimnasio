<?php
include 'conexion.php';
include 'menu_horizontal.php';

$query = "SELECT u.id, u.usuario, u.rol, g.nombre AS gimnasio,
                 u.clientes, u.membresias, u.profesores, u.ventas, u.asistencias
          FROM usuarios u 
          LEFT JOIN gimnasios g ON u.id_gimnasio = g.id";
$resultado = $conexion->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios por Gimnasio</title>
  <style>
    body { font-family: Arial, sans-serif; background-color: #111; color: #f1f1f1; padding: 20px; }
    h2 { color: gold; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 10px; border: 1px solid #444; text-align: left; }
    th { background-color: gold; color: black; }
    tr:nth-child(even) { background-color: #222; }
    tr:hover { background-color: #333; }
    a.boton { padding: 6px 12px; background: gold; color: black; text-decoration: none; border-radius: 4px; margin-right: 5px; }
    a.boton:hover { background: #ffd700; }
  </style>
</head>
<body>
  <h2>Usuarios por Gimnasio</h2>
  <table>
    <tr>
      <th>Usuario</th>
      <th>Rol</th>
      <th>Gimnasio</th>
      <th>Clientes</th>
      <th>Membresías</th>
      <th>Profesores</th>
      <th>Ventas</th>
      <th>Asistencias</th>
      <th>Acciones</th>
    </tr>
    <?php while($row = $resultado->fetch_assoc()) { ?>
      <tr>
        <td><?php echo htmlspecialchars($row['usuario']); ?></td>
        <td><?php echo htmlspecialchars($row['rol']); ?></td>
        <td><?php echo htmlspecialchars($row['gimnasio'] ?? 'Sin asignar'); ?></td>
        <td><?= $row['clientes'] ? '✔️' : '❌' ?></td>
        <td><?= $row['membresias'] ? '✔️' : '❌' ?></td>
        <td><?= $row['profesores'] ? '✔️' : '❌' ?></td>
        <td><?= $row['ventas'] ? '✔️' : '❌' ?></td>
        <td><?= $row['asistencias'] ? '✔️' : '❌' ?></td>
        <td>
          <a class="boton" href="editar_usuario.php?id=<?php echo $row['id']; ?>">Editar</a>
          <a class="boton" href="eliminar_usuario.php?id=<?php echo $row['id']; ?>" onclick="return confirm('¿Seguro que deseas eliminar este usuario?')">Eliminar</a>
        </td>
      </tr>
    <?php } ?>
  </table>
</body>
</html>
