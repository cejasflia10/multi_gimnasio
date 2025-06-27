<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("conexion.php");

// Obtener gimnasios disponibles
$gimnasios_resultado = $conexion->query("SELECT id, nombre FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Usuario</title>
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
            margin-top: 10px;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .permisos-box {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            background: #222;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .permisos-box label {
            margin: 0;
        }
        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

<h2>Agregar Nuevo Usuario</h2>

<form action="guardar_nuevo_usuario.php" method="POST">

    <label>Nombre de Usuario</label>
    <input type="text" name="nombre_usuario" required>

    <label>Contraseña</label>
    <input type="password" name="contrasena" required>

    <label>Rol</label>
    <select name="rol" required>
        <option value="admin">Admin</option>
        <option value="usuario">Usuario</option>
        <option value="profesor">Profesor</option>
    </select>

    <label>Asignar Gimnasio</label>
    <select name="gimnasio_id" required>
        <?php while ($g = $gimnasios_resultado->fetch_assoc()): ?>
            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nombre']) ?></option>
        <?php endwhile; ?>
    </select>

    <label>Permisos específicos</label>
    <div class="permisos-box">
        <?php
        $opciones = ['clientes', 'membresias', 'qr', 'asistencias', 'profesores', 'ventas', 'panel'];
        foreach ($opciones as $permiso) {
            echo "<label><input type='checkbox' name='permisos[]' value='$permiso'> $permiso</label>";
        }
        ?>
    </div>

    <button type="submit">Crear Usuario</button>
</form>

</body>
</html>
