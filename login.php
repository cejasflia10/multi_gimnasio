<?php
session_start();
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login - Multi Gimnasio</title>
</head>
<body>
  <h2>Ingreso</h2>
  <?php if ($error == 1): ?>
    <p style="color:red;">Por favor complete ambos campos.</p>
  <?php elseif ($error == 2): ?>
    <p style="color:red;">Contraseña incorrecta.</p>
  <?php elseif ($error == 3): ?>
    <p style="color:red;">Usuario no encontrado.</p>
  <?php endif; ?>
  <form action="login_seguro.php" method="post">
    <input type="text" name="usuario" placeholder="Usuario" required><br>
    <input type="password" name="contrasena" placeholder="Contraseña" required><br>
    <button type="submit">Ingresar</button>
  </form>
</body>
</html>
