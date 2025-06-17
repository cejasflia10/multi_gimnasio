<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die(".");
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $email = trim($_POST["email"]);
    $clave = password_hash(trim($_POST["clave"]), PASSWORD_BCRYPT);
    $rol = $_POST["rol"];
    $gimnasio_id = $_POST["gimnasio_id"];

    $permiso_clientes = isset($_POST['permiso_clientes']) ? 1 : 0;
    $permiso_membresias = isset($_POST['permiso_membresias']) ? 1 : 0;
    $permiso_profesores = isset($_POST['permiso_profesores']) ? 1 : 0;
    $permiso_ventas = isset($_POST['permiso_ventas']) ? 1 : 0;
    $permiso_panel = isset($_POST['permiso_panel']) ? 1 : 0;
    $permiso_asistencias = isset($_POST['permiso_asistencias']) ? 1 : 0;

    $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, email, contrasena, rol, id_gimnasio, permiso_clientes, permiso_membresias, perm_profesores, perm_ventas, puede_ver_panel, puede_ver_asistencias) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiiiiiii", $usuario, $email, $clave, $rol, $gimnasio_id, $permiso_clientes, $permiso_membresias, $permiso_profesores, $permiso_ventas, $permiso_panel, $permiso_asistencias);

    if ($stmt->execute()) {
        echo "<script>alert('Usuario creado exitosamente'); window.location.href='usuarios.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Usuario</title>
  <style>
    body { background-color: #111; color: #f1f1f1; font-family: Arial; padding: 30px; }
    .form-container { background-color: #222; padding: 25px; border-radius: 10px; max-width: 600px; margin: auto; box-shadow: 0 0 15px rgba(255,215,0,0.3); }
    h2 { color: gold; text-align: center; }
    label { display: block; margin-top: 15px; font-weight: bold; }
    input, select { width: 100%; padding: 10px; margin-top: 5px; background-color: #333; border: none; border-radius: 5px; color: white; }
    input[type=checkbox] { width: auto; margin-right: 10px; }
    button { margin-top: 25px; padding: 12px; width: 100%; background-color: gold; color: black; font-weight: bold; border: none; border-radius: 8px; cursor: pointer; }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Agregar Usuario</h2>
    <form method="post">
      <label>Nombre de Usuario:
        <input type="text" name="usuario" required>
      </label>
      <label>Email:
        <input type="email" name="email">
      </label>
      <label>Contraseña:
        <input type="password" name="clave" required>
      </label>
      <label>Rol:
        <select name="rol">
          <option value="admin">Administrador</option>
          <option value="profesor">Profesor</option>
          <option value="instructor">Instructor</option>
        </select>
      </label>
      <label>Gimnasio:
        <select name="gimnasio_id">
          <?php while ($g = $gimnasios->fetch_assoc()) { ?>
            <option value="<?php echo $g['id']; ?>"><?php echo $g['nombre']; ?></option>
          <?php } ?>
        </select>
      </label>

      <h3>Permisos</h3>
      <label><input type="checkbox" name="permiso_clientes" value="1"> Ver Clientes</label>
      <label><input type="checkbox" name="permiso_membresias" value="1"> Ver Membresías</label>
      <label><input type="checkbox" name="permiso_ventas" value="1"> Ver Ventas</label>
      <label><input type="checkbox" name="permiso_profesores" value="1"> Ver Profesores</label>
      <label><input type="checkbox" name="permiso_panel" value="1"> Ver Panel</label>
      <label><input type="checkbox" name="permiso_asistencias" value="1"> Ver Asistencias</label>

      <button type="submit">Crear Usuario</button>
    </form>
  </div>
</body>
</html>
