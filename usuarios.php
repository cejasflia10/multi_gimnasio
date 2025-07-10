<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';



$query = "SELECT u.id, u.usuario, u.rol, g.nombre AS gimnasio 
          FROM usuarios u
          LEFT JOIN gimnasios g ON u.id_gimnasio = g.id";

$resultado = $conexion->query($query);
if (!$resultado) {
    die("Error al obtener usuarios: " . $conexion->error);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios por Gimnasio</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
  <h2 class="titulo-seccion">👥 Usuarios por Gimnasio</h2>

  <div class="tabla-contenedor">
    <table>
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Rol</th>
          <th>Gimnasio</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while($row = $resultado->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['usuario']) ?></td>
            <td><?= htmlspecialchars($row['rol']) ?></td>
            <td><?= htmlspecialchars($row['gimnasio'] ?? 'Sin asignar') ?></td>
            <td>
              <a class="btn-editar" href="editar_usuario.php?id=<?= $row['id'] ?>">✏️ Editar</a>
              <a class="btn-eliminar" href="eliminar_usuario.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar este usuario?')">🗑️ Eliminar</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
