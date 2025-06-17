<?php
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID de usuario no especificado.");
}
$id = $_GET['id'];

$resultado = $conexion->query("SELECT * FROM usuarios WHERE id = $id");
if ($resultado->num_rows === 0) {
    die("Usuario no encontrado.");
}
$usuario = $resultado->fetch_assoc();

$usuario_nombre = $usuario['usuario'] ?? '';
$usuario_email = $usuario['email'] ?? '';
$usuario_rol = $usuario['rol'] ?? '';
$usuario_gimnasio_id = $usuario['id_gimnasio'] ?? 0;

$permiso_clientes = $usuario['permiso_clientes'] ?? 0;
$permiso_membresias = $usuario['permiso_membresias'] ?? 0;
$permiso_profesores = $usuario['perm_profesores'] ?? 0;
$permiso_ventas = $usuario['perm_ventas'] ?? 0;
$permiso_panel = $usuario['puede_ver_panel'] ?? 0;
$permiso_asistencias = $usuario['puede_ver_asistencias'] ?? 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST["usuario"];
    $email = $_POST["email"];
    $rol = $_POST["rol"];
    $gimnasio_id = $_POST["gimnasio_id"];

    $permiso_clientes = isset($_POST['permiso_clientes']) ? 1 : 0;
    $permiso_membresias = isset($_POST['permiso_membresias']) ? 1 : 0;
    $permiso_profesores = isset($_POST['permiso_profesores']) ? 1 : 0;
    $permiso_ventas = isset($_POST['permiso_ventas']) ? 1 : 0;
    $permiso_panel = isset($_POST['permiso_panel']) ? 1 : 0;
    $permiso_asistencias = isset($_POST['permiso_asistencias']) ? 1 : 0;

    $stmt = $conexion->prepare("UPDATE usuarios SET usuario=?, email=?, rol=?, id_gimnasio=?, permiso_clientes=?, permiso_membresias=?, perm_profesores=?, perm_ventas=?, puede_ver_panel=?, puede_ver_asistencias=? WHERE id=?");
    $stmt->bind_param("sssiiiiiiii", $nombre, $email, $rol, $gimnasio_id, $permiso_clientes, $permiso_membresias, $permiso_profesores, $permiso_ventas, $permiso_panel, $permiso_asistencias, $id);
    $stmt->execute();

    header("Location: usuarios.php");
    exit;
}

$gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Usuario</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      padding: 30px;
    }
    .form-container {
      background-color: #222;
      padding: 25px;
      border-radius: 10px;
      max-width: 600px;
      margin: auto;
      box-shadow: 0 0 15px rgba(255,215,0,0.3);
    }
    h2 {
      color: gold;
      text-align: center;
    }
    label {
      display: block;
      margin-top: 15px;
      font-weight: bold;
    }
    input, select {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      background-color: #333;
      border: none;
      border-radius: 5px;
      color: white;
    }
    input[type=checkbox] {
      width: auto;
      margin-right: 10px;
    }
    button {
      margin-top: 25px;
      padding: 12px;
      width: 100%;
      background-color: gold;
      color: black;
      font-weight: bold;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Editar Usuario</h2>
    <form method="post">
      <label>Nombre:
        <input type="text" name="usuario" value="<?php echo htmlspecialchars($usuario_nombre); ?>" required>
      </label>
      <label>Email:
        <input type="email" name="email" value="<?php echo htmlspecialchars($usuario_email); ?>">
      </label>
      <label>Rol:
        <select name="rol">
          <option value="admin" <?php if ($usuario_rol === "admin") echo "selected"; ?>>Administrador</option>
          <option value="profesor" <?php if ($usuario_rol === "profesor") echo "selected"; ?>>Profesor</option>
          <option value="instructor" <?php if ($usuario_rol === "instructor") echo "selected"; ?>>Instructor</option>
        </select>
      </label>
      <label>Gimnasio:
        <select name="gimnasio_id">
          <?php while ($g = $gimnasios->fetch_assoc()) { ?>
            <option value="<?php echo $g['id']; ?>" <?php if ($usuario_gimnasio_id == $g['id']) echo "selected"; ?>>
              <?php echo $g['nombre']; ?>
            </option>
          <?php } ?>
        </select>
      </label>

      <h3>Permisos</h3>
      <label><input type="checkbox" name="permiso_clientes" value="1" <?php if ($permiso_clientes) echo "checked"; ?>> Ver Clientes</label>
      <label><input type="checkbox" name="permiso_membresias" value="1" <?php if ($permiso_membresias) echo "checked"; ?>> Ver Membresías</label>
      <label><input type="checkbox" name="permiso_ventas" value="1" <?php if ($permiso_ventas) echo "checked"; ?>> Ver Ventas</label>
      <label><input type="checkbox" name="permiso_profesores" value="1" <?php if ($permiso_profesores) echo "checked"; ?>> Ver Profesores</label>
      <label><input type="checkbox" name="permiso_panel" value="1" <?php if ($permiso_panel) echo "checked"; ?>> Ver Panel</label>
      <label><input type="checkbox" name="permiso_asistencias" value="1" <?php if ($permiso_asistencias) echo "checked"; ?>> Ver Asistencias</label>

      <button type="submit">Guardar Cambios</button>
    </form>
  </div>
</body>
</html>
