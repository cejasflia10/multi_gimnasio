<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario']) ||
   ($_SESSION['usuario'] !== 'fightacademy' && $_SESSION['usuario'] !== 'lucianoc')) {
    echo "<p style='color:red; text-align:center; font-size:20px;'>🚫 No tienes permisos para acceder a esta página.</p>";
    exit;
}

include 'conexion.php';
include 'menu_eventos.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre  = trim($_POST['nombre']);
    // $email   = trim($_POST['email']);  // ELIMINADO
    $usuario = strtolower(trim($_POST['usuario']));
    $clave   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $rol     = $_POST['rol'];

    $stmt = $conexion->prepare("INSERT INTO usuarios_eventos (nombre,usuario,clave,rol) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $nombre, $usuario, $clave, $rol);

    if ($stmt->execute()) {
        $mensaje = "✅ Usuario creado correctamente.";
    } else {
        $mensaje = "❌ Error al crear el usuario: " . $conexion->error;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Crear Usuario de Evento</title>
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
  <h2>➕ Crear Usuario de Evento</h2>
  <?php if ($mensaje): ?><p class="mensaje"><?= $mensaje ?></p><?php endif; ?>

  <form method="POST">
      <label>Nombre:</label>
      <input type="text" name="nombre" required>

      <!-- Campo email eliminado -->

      <label>Usuario:</label>
      <input type="text" name="usuario" required>

      <label>Contraseña:</label>
      <input type="password" name="password" required>

      <label>Rol:</label>
      <select name="rol" required>
          <option value="organizador">Organizador</option>
          <option value="juez">Juez</option>
          <option value="staff">Staff</option>
      </select>

      <button type="submit">✅ Guardar Usuario</button>
  </form>
</div>
</body>
</html>
