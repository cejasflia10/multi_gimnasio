<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("conexion.php");
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}
// Obtener gimnasios disponibles
$gimnasios_resultado = $conexion->query("SELECT id, nombre FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Agregar Usuario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
</head>
<body>
<div class="contenedor">

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
    </div>

</body>
</html>
