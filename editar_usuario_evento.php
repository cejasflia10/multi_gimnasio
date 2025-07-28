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

$id = $_GET['id'];
$usuario = $conexion->query("SELECT * FROM usuarios_evento WHERE id=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $usuario_n = $_POST['usuario'];
    $rol = $_POST['rol'];

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios_evento SET nombre='$nombre', email='$email', usuario='$usuario_n', password='$password', rol='$rol' WHERE id=$id";
    } else {
        $sql = "UPDATE usuarios_evento SET nombre='$nombre', email='$email', usuario='$usuario_n', rol='$rol' WHERE id=$id";
    }

    if ($conexion->query($sql)) {
        header("Location: ver_usuarios_evento.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Usuario</title>
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
  <h2>✏️ Editar Usuario</h2>
  <form method="POST">
      <label>Nombre:</label>
      <input type="text" name="nombre" value="<?= $usuario['nombre'] ?>" required>

      <label>Email:</label>
      <input type="email" name="email" value="<?= $usuario['email'] ?>">

      <label>Usuario:</label>
      <input type="text" name="usuario" value="<?= $usuario['usuario'] ?>" required>

      <label>Nueva Contraseña (opcional):</label>
      <input type="password" name="password">

      <label>Rol:</label>
      <select name="rol">
          <option value="organizador" <?= $usuario['rol']=='organizador'?'selected':'' ?>>Organizador</option>
          <option value="juez" <?= $usuario['rol']=='juez'?'selected':'' ?>>Juez</option>
          <option value="staff" <?= $usuario['rol']=='staff'?'selected':'' ?>>Staff</option>
      </select>

      <button type="submit">💾 Guardar Cambios</button>
  </form>
</div>
</body>
</html>
