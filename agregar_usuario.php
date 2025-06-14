<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Usuario</title>
  <style>
    body {
      background-color: #111;
      color: #fff;
      font-family: Arial;
    }
    .form-container {
      max-width: 500px;
      margin: 40px auto;
      background-color: #222;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 10px gold;
    }
    label {
      display: block;
      margin-top: 10px;
    }
    input, select {
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      border-radius: 6px;
      border: none;
    }
    .checkbox-group {
      margin-top: 15px;
    }
    .checkbox-group label {
      display: block;
      margin-bottom: 5px;
    }
    .btn {
      margin-top: 20px;
      padding: 10px;
      border: none;
      width: 100%;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
    }
    .btn-success { background-color: gold; color: #111; }
  </style>
</head>
<body>

<div class="form-container">
  <h2>Agregar Nuevo Usuario</h2>
  <form action="guardar_usuario.php" method="post">
    <label>Nombre de Usuario:</label>
    <input type="text" name="nombre_usuario" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Contraseña:</label>
    <input type="password" name="contrasena" required>

    <label>Confirmar Contraseña:</label>
    <input type="password" name="confirmar_contrasena" required>

    <label>Rol:</label>
    <select name="rol" required>
      <option value="Administrador">Administrador</option>
      <option value="Profesor">Profesor</option>
      <option value="Instructor">Instructor</option>
    </select>
<label>Gimnasio:</label>
<select name="id_gimnasio" required>
  <?php
    include("conexion.php");
    $result = $conexion->query("SELECT id, nombre FROM gimnasios");
    while ($row = $result->fetch_assoc()):
  ?>
    <option value="<?= $row['id'] ?>"><?= $row['nombre'] ?></option>
  <?php endwhile; ?>
</select>

    <div class="checkbox-group">
      <label><input type="checkbox" name="puede_ver_clientes" value="1"> Ver Clientes</label>
      <label><input type="checkbox" name="puede_ver_membresias" value="1"> Ver Membresías</label>
      <label><input type="checkbox" name="puede_ver_profesores" value="1"> Ver Profesores</label>
      <label><input type="checkbox" name="puede_ver_ventas" value="1"> Ver Ventas</label>
      <label><input type="checkbox" name="puede_ver_asistencias" value="1"> Ver Asistencias</label>
      <label><input type="checkbox" name="puede_ver_panel" value="1"> Ver Panel</label>
      <label><input type="checkbox" name="puede_ver_admin" value="1"> Acceso Admin</label>
    </div>

    <button type="submit" class="btn btn-success">Crear Usuario</button>
  </form>
</div>

</body>
</html>
