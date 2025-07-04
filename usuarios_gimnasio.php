<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('usuarios_gimnasio')) {
    echo "<h2 style='color:red;'>Acceso denegado</h2>";
    exit;
}
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

  <?php
  $resultado = $conexion->query("
      SELECT u.*, g.nombre AS gimnasio
      FROM usuarios u
      LEFT JOIN gimnasios g ON u.gimnasio_id = g.id
  ");

  if (!$resultado) {
      echo "<div style='color:red;'>❌ Error en la consulta: " . $conexion->error . "</div>";
      exit;
  }
  ?>

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
        <td><?= htmlspecialchars($row['usuario']) ?></td>
        <td><?= htmlspecialchars($row['rol']) ?></td>
        <td><?= htmlspecialchars($row['gimnasio'] ?? 'Sin asignar') ?></td>
        <td><?= !empty($row['clientes']) ? '✔️' : '❌' ?></td>
        <td><?= !empty($row['membresias']) ? '✔️' : '❌' ?></td>
        <td><?= !empty($row['profesores']) ? '✔️' : '❌' ?></td>
        <td><?= !empty($row['ventas']) ? '✔️' : '❌' ?></td>
        <td><?= !empty($row['asistencias']) ? '✔️' : '❌' ?></td>
        <td>
          <a class="boton" href="editar_usuario.php?id=<?= $row['id'] ?>">Editar</a>
          <a class="boton" href="eliminar_usuario.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar este usuario?')">Eliminar</a>
        </td>
      </tr>
    <?php } ?>
  </table>
</body>
</html>
