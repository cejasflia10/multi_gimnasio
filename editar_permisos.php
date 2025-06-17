<?php
session_start();
include 'conexion.php';

if (!isset($_GET['usuario_id'])) {
    die("Falta el ID de usuario.");
}

$usuario_id = $_GET['usuario_id'];

// Obtener gimnasios
$gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");

// Guardar permisos si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $gimnasio_id = $_POST['gimnasio_id'];
    $ver_clientes = isset($_POST['ver_clientes']) ? 1 : 0;
    $editar_clientes = isset($_POST['editar_clientes']) ? 1 : 0;
    $ver_membresias = isset($_POST['ver_membresias']) ? 1 : 0;
    $editar_membresias = isset($_POST['editar_membresias']) ? 1 : 0;
    $ver_ventas = isset($_POST['ver_ventas']) ? 1 : 0;
    $editar_ventas = isset($_POST['editar_ventas']) ? 1 : 0;

    $existe = $conexion->query("SELECT id FROM permisos_usuario WHERE usuario_id=$usuario_id AND gimnasio_id=$gimnasio_id");
    if ($existe->num_rows > 0) {
        $conexion->query("UPDATE permisos_usuario SET 
            puede_ver_clientes=$ver_clientes, 
            puede_editar_clientes=$editar_clientes, 
            puede_ver_membresias=$ver_membresias,
            puede_editar_membresias=$editar_membresias,
            puede_ver_ventas=$ver_ventas,
            puede_editar_ventas=$editar_ventas
            WHERE usuario_id=$usuario_id AND gimnasio_id=$gimnasio_id");
    } else {
        $conexion->query("INSERT INTO permisos_usuario 
            (usuario_id, gimnasio_id, puede_ver_clientes, puede_editar_clientes, puede_ver_membresias, puede_editar_membresias, puede_ver_ventas, puede_editar_ventas)
            VALUES ($usuario_id, $gimnasio_id, $ver_clientes, $editar_clientes, $ver_membresias, $editar_membresias, $ver_ventas, $editar_ventas)");
    }

    echo "<script>alert('Permisos actualizados');</script>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Permisos</title>
  <style>
    body { background-color: #111; color: #fff; font-family: Arial; padding: 20px; }
    form { background: #222; padding: 20px; border-radius: 10px; max-width: 500px; margin: auto; }
    label { display: block; margin: 10px 0 5px; }
    select, input[type="checkbox"] { margin-bottom: 10px; }
    button { padding: 10px 20px; background: gold; border: none; border-radius: 5px; font-weight: bold; }
  </style>
</head>
<body>
  <h2 style="text-align:center;">Asignar Permisos por Gimnasio</h2>
  <form method="post">
    <label for="gimnasio_id">Seleccionar gimnasio:</label>
    <select name="gimnasio_id" required>
      <option value="">-- Elegir --</option>
      <?php while($g = $gimnasios->fetch_assoc()): ?>
        <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label><input type="checkbox" name="ver_clientes"> Ver Clientes</label>
    <label><input type="checkbox" name="editar_clientes"> Editar Clientes</label>
    <label><input type="checkbox" name="ver_membresias"> Ver Membresías</label>
    <label><input type="checkbox" name="editar_membresias"> Editar Membresías</label>
    <label><input type="checkbox" name="ver_ventas"> Ver Ventas</label>
    <label><input type="checkbox" name="editar_ventas"> Editar Ventas</label>

    <button type="submit">Guardar Permisos</button>
  </form>
</body>
</html>
