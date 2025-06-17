<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Usuario</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h2 {
      color: gold;
    }
    form {
      background-color: #222;
      padding: 20px;
      border-radius: 10px;
      max-width: 500px;
    }
    label {
      display: block;
      margin-top: 15px;
      color: #f1f1f1;
    }
    input, select {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      background-color: #333;
      border: none;
      color: white;
      border-radius: 5px;
    }
    button {
      margin-top: 20px;
      padding: 10px 20px;
      background-color: gold;
      color: black;
      font-weight: bold;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    a {
      color: #ccc;
      display: inline-block;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <h2>Agregar Nuevo Usuario</h2>
  <form action="guardar_usuario.php" method="post">
    <label for="usuario">Nombre de Usuario:</label>
    <input type="text" name="usuario" id="usuario" required>

    <label for="clave">Contraseña:</label>
    <input type="password" name="clave" id="clave" required>

    <label for="rol">Rol:</label>
    <select name="rol" id="rol" required>
      <option value="admin">Administrador</option>
      <option value="profesor">Profesor</option>
      <option value="instructor">Instructor</option>
    </select>

    <label>Permisos del Usuario:</label>
    <label><input type="checkbox" name="permiso_clientes" value="1"> Ver Clientes</label>
    <label><input type="checkbox" name="permiso_membresias" value="1"> Ver Membresías</label>
    <label><input type="checkbox" name="permiso_ventas" value="1"> Ver Ventas</label>
    <label><input type="checkbox" name="permiso_profesores" value="1"> Ver Profesores</label>
    <label><input type="checkbox" name="permiso_panel" value="1"> Ver Panel</label>
    <label><input type="checkbox" name="permiso_asistencias" value="1"> Ver Asistencias</label>

    <button type="submit">Guardar Usuario</button>
    <a href="usuarios.php">← Volver al listado</a>
  </form>
</body>
</html>
