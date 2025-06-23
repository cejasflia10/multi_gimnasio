<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}
include 'conexion.php';

$id = $_GET['id'] ?? 0;
$query = $conexion->prepare("SELECT * FROM usuarios WHERE id = ?");
$query->bind_param("i", $id);
$query->execute();
$resultado = $query->get_result();
$usuarioData = $resultado->fetch_assoc();

$gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = $_POST["usuario"];
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
    $stmt->bind_param("sssiiiiiiii", $usuario, $email, $rol, $gimnasio_id, $permiso_clientes, $permiso_membresias, $permiso_profesores, $permiso_ventas, $permiso_panel, $permiso_asistencias, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Usuario actualizado correctamente'); window.location.href='ver_usuarios.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .form-container { background-color: #222; padding: 25px; border-radius: 10px; max-width: 600px; margin: auto; box-shadow: 0 0 15px rgba(255,215,0,0.2); }
        h2 { text-align: center; }
        label { display: block; margin-top: 15px; }
        input, select { width: 100%; padding: 10px; background-color: #333; color: gold; border: 1px solid gold; border-radius: 5px; }
        input[type="checkbox"] { width: auto; margin-right: 10px; }
        button { margin-top: 25px; width: 100%; padding: 12px; background-color: gold; color: black; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Editar Usuario</h2>
    <form method="post">
        <label>Usuario:
            <input type="text" name="usuario" value="<?= htmlspecialchars($usuarioData['usuario']) ?>" required>
        </label>
        <label>Email:
            <input type="email" name="email" value="<?= htmlspecialchars($usuarioData['email']) ?>">
        </label>
        <label>Rol:
            <select name="rol" required>
                <option value="admin" <?= $usuarioData['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                <option value="cliente_gym" <?= $usuarioData['rol'] === 'cliente_gym' ? 'selected' : '' ?>>Cliente Gym</option>
                <option value="profesor" <?= $usuarioData['rol'] === 'profesor' ? 'selected' : '' ?>>Profesor</option>
            </select>
        </label>
        <label>Gimnasio:
            <select name="gimnasio_id">
                <?php while ($g = $gimnasios->fetch_assoc()) { ?>
                    <option value="<?= $g['id'] ?>" <?= $usuarioData['id_gimnasio'] == $g['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['nombre']) ?>
                    </option>
                <?php } ?>
            </select>
        </label>

        <h3>Permisos</h3>
        <label><input type="checkbox" name="permiso_clientes" <?= $usuarioData['permiso_clientes'] ? 'checked' : '' ?>> Ver Clientes</label>
        <label><input type="checkbox" name="permiso_membresias" <?= $usuarioData['permiso_membresias'] ? 'checked' : '' ?>> Ver Membresías</label>
        <label><input type="checkbox" name="permiso_ventas" <?= $usuarioData['perm_ventas'] ? 'checked' : '' ?>> Ver Ventas</label>
        <label><input type="checkbox" name="permiso_profesores" <?= $usuarioData['perm_profesores'] ? 'checked' : '' ?>> Ver Profesores</label>
        <label><input type="checkbox" name="permiso_panel" <?= $usuarioData['puede_ver_panel'] ? 'checked' : '' ?>> Ver Panel</label>
        <label><input type="checkbox" name="permiso_asistencias" <?= $usuarioData['puede_ver_asistencias'] ? 'checked' : '' ?>> Ver Asistencias</label>

        <button type="submit">Guardar Cambios</button>
    </form>
</div>

</body>
</html>
