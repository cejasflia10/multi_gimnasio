<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}
$id = $_GET['id'] ?? 0;
$id = intval($id);

$result = $conexion->query("SELECT * FROM usuarios WHERE id = $id LIMIT 1");
if ($result->num_rows === 0) {
    die("Usuario no encontrado.");
}
$usuario = $result->fetch_assoc();
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
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 550px;
            margin: auto;
            background: #222;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #000;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            background-color: #111;
            color: gold;
            border: 1px solid gold;
            border-radius: 5px;
            margin-top: 5px;
        }
        .permisos-box {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            background: #111;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .permisos-box label {
            font-weight: normal;
        }
        .btn-guardar, .btn-eliminar {
            width: 100%;
            padding: 12px;
            font-weight: bold;
            border: none;
            margin-top: 20px;
            cursor: pointer;
            border-radius: 5px;
        }
        .btn-guardar {
            background-color: gold;
            color: #111;
        }
        .btn-eliminar {
            background-color: red;
            color: white;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Editar Usuario</h2>

    <form action="guardar_usuario.php" method="POST">
        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">

        <label>Usuario</label>
        <input type="text" name="usuario" value="<?= htmlspecialchars($usuario['usuario'] ?? '') ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">

        <label>Contraseña nueva (opcional)</label>
        <input type="password" name="nueva_contrasena">

        <label>Rol</label>
        <select name="rol" required>
            <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="usuario" <?= $usuario['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
            <option value="profesor" <?= $usuario['rol'] === 'profesor' ? 'selected' : '' ?>>Profesor</option>
        </select>

        <label>Permisos habilitados:</label>
        <div class="permisos-box">
            <?php
            $checks = [
                'permiso_clientes' => 'Clientes',
                'permiso_membresias' => 'Membresías',
                'permiso_profesores' => 'Profesores',
                'permiso_ventas' => 'Ventas',
                'permiso_asistencias' => 'Asistencias',
                'permiso_panel' => 'Panel'
            ];
            foreach ($checks as $campo => $texto) {
                $checked = $usuario[$campo] ? 'checked' : '';
                echo "<label><input type='checkbox' name='$campo' value='1' $checked> $texto</label>";
            }
            ?>
        </div>

        <button type="submit" class="btn-guardar">💾 Guardar Cambios</button>
    </form>

    <form action="eliminar_usuario.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?')">
        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
        <button type="submit" class="btn-eliminar">🗑️ Eliminar Usuario</button>
    </form>
</div>

</body>
</html>
