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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST["usuario"];
    $email = $_POST["email"];
    $rol = $_POST["rol"];
    $gimnasio_id = $_POST["gimnasio_id"];

    $permiso_clientes = isset($_POST['permiso_clientes']) ? 1 : 0;
    $permiso_membresias = isset($_POST['permiso_membresias']) ? 1 : 0;
    $permiso_ventas = isset($_POST['permiso_ventas']) ? 1 : 0;
    $permiso_profesores = isset($_POST['permiso_profesores']) ? 1 : 0;
    $permiso_panel = isset($_POST['permiso_panel']) ? 1 : 0;
    $permiso_asistencias = isset($_POST['permiso_asistencias']) ? 1 : 0;

    $stmt = $conexion->prepare("UPDATE usuarios SET usuario=?, email=?, rol=?, gimnasio_id=?, permiso_clientes=?, permiso_membresias=?, permiso_ventas=?, permiso_profesores=?, permiso_panel=?, permiso_asistencias=? WHERE id=?");
    $stmt->bind_param("sssiiiiiiii", $nombre, $email, $rol, $gimnasio_id, $permiso_clientes, $permiso_membresias, $permiso_ventas, $permiso_profesores, $permiso_panel, $permiso_asistencias, $id);
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
    body { background-color: #111; color: #f1f1f1; font-family: Arial; padding: 20px; }
    form { background-color: #222; padding: 20px; border-radius: 10px; max-width: 600px; }
    input, label, select { display: block; width: 100%; margin-top: 10px; }
    input[type=checkbox] { width: auto; }
    button { margin-top: 15px; padding: 10px; background: gold; color: black; border: none; border-radius: 5px; font-weight: bold; }
  </style>
</head>
<body>
  <h2>Editar Usuario</h2>
  <form method="post">
    <label>Nombre:
      <input type="text" name="usuario" value="<?php echo htmlspecialchars($usuario['usuario']); ?>" required>
    </label>
    <label>Email:
      <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>">
    </label>
    <label>Rol:
      <select name="rol">
        <option value="admin" <?php if ($usuario["rol"]=="admin") echo "selected"; ?>>Administrador</option>
        <option value="profesor" <?php if ($usuario["rol"]=="profesor") echo "selected"; ?>>Profesor</option>
        <option value="instructor" <?php if ($usuario["rol"]=="instructor") echo "selected"; ?>>Instructor</option>
      </select>
    </label>
    <label>Gimnasio:
      <select name="gimnasio_id">
        <?php while ($g = $gimnasios->fetch_assoc()) { ?>
          <option value="<?php echo $g['id']; ?>" <?php if ($usuario['gimnasio_id'] == $g['id']) echo "selected"; ?>>
            <?php echo $g['nombre']; ?>
          </option>
        <?php } ?>
      </select>
    </label>

    <h3>Permisos</h3>
    <label><input type="checkbox" name="permiso_clientes" value="1" <?php if ($usuario['permiso_clientes']) echo "checked"; ?>> Ver Clientes</label>
    <label><input type="checkbox" name="permiso_membresias" value="1" <?php if ($usuario['permiso_membresias']) echo "checked"; ?>> Ver Membresías</label>
    <label><input type="checkbox" name="permiso_ventas" value="1" <?php if ($usuario['permiso_ventas']) echo "checked"; ?>> Ver Ventas</label>
    <label><input type="checkbox" name="permiso_profesores" value="1" <?php if ($usuario['permiso_profesores']) echo "checked"; ?>> Ver Profesores</label>
    <label><input type="checkbox" name="permiso_panel" value="1" <?php if ($usuario['permiso_panel']) echo "checked"; ?>> Ver Panel</label>
    <label><input type="checkbox" name="permiso_asistencias" value="1" <?php if ($usuario['permiso_asistencias']) echo "checked"; ?>> Ver Asistencias</label>

    <button type="submit">Guardar Cambios</button>
  </form>
</body>
</html>
