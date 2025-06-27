<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include("conexion.php");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Mostrar errores

include 'menu_horizontal.php';

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de usuario no especificado o no válido.");
}

$id = intval($_GET['id']);

// Traer usuario
$query = "SELECT * FROM usuarios WHERE id = $id";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Usuario no encontrado.");
}

$usuario = $resultado->fetch_assoc();

// Traer gimnasios
$gimnasios_resultado = $conexion->query("SELECT id, nombre FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
        }
        select, input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        button {
            background-color: gold;
            border: none;
            padding: 10px 20px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <h2>Editar Usuario</h2>

    <form action="guardar_usuario.php" method="POST">
        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">

        <label>Nombre de Usuario</label>
        <input type="text" name="nombre_usuario" value="<?= htmlspecialchars($usuario['nombre_usuario']) ?>" required>

        <label>Rol</label>
        <select name="rol" required>
            <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="usuario" <?= $usuario['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
            <option value="profesor" <?= $usuario['rol'] === 'profesor' ? 'selected' : '' ?>>Profesor</option>
        </select>

        <label>Asignar Gimnasio</label>
        <select name="gimnasio_id" required>
            <?php while ($g = $gimnasios_resultado->fetch_assoc()): ?>
                <option value="<?= $g['id'] ?>" <?= ($g['id'] == $usuario['gimnasio_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['nombre']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit">Guardar Cambios</button>
    </form>

</body>
</html>
