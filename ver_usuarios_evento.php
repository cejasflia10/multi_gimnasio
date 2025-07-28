<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// 🔒 SOLO fightacademy y lucianoc pueden entrar
if (!isset($_SESSION['usuario']) || 
   ($_SESSION['usuario'] !== 'fightacademy' && $_SESSION['usuario'] !== 'lucianoc')) {
    echo "<p style='color:red; text-align:center; font-size:20px;'>🚫 No tienes permisos para acceder a esta página.</p>";
    exit;
}

include 'conexion.php';
include 'menu_eventos.php';

$usuarios = $conexion->query("SELECT * FROM usuarios_evento ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios del Evento</title>
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
  <h2>👥 Usuarios del Evento</h2>
  <a href="crear_usuario_evento.php">➕ Nuevo Usuario</a>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Nombre</th><th>Email</th><th>Usuario</th><th>Rol</th><th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($u = $usuarios->fetch_assoc()): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= $u['nombre'] ?></td>
        <td><?= $u['email'] ?></td>
        <td><?= $u['usuario'] ?></td>
        <td><?= ucfirst($u['rol']) ?></td>
        <td>
          <a href="editar_usuario_evento.php?id=<?= $u['id'] ?>">✏️ Editar</a> |
          <a href="eliminar_usuario_evento.php?id=<?= $u['id'] ?>" onclick="return confirm('¿Eliminar usuario?')">🗑 Eliminar</a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
