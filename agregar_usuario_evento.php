<?php
session_start();
include 'conexion.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = trim($_POST['clave'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $rol = $_POST['rol'] ?? 'organizador';

    if ($usuario && $clave && $nombre) {
        // Encriptar contraseña
        $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("INSERT INTO usuarios_eventos (usuario, clave, nombre, rol) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $usuario, $clave_hash, $nombre, $rol);

        if ($stmt->execute()) {
            $mensaje = "✅ Usuario creado correctamente.";
        } else {
            $mensaje = "❌ Error al crear el usuario (¿ya existe?).";
        }
    } else {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Usuario - Eventos</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body style="background: black; color: gold;">
<div class="contenedor" style="max-width: 500px;">
    <h2>👤 Crear Usuario para Panel de Eventos</h2>
    <?php if ($mensaje) echo "<p style='color: gold;'>$mensaje</p>"; ?>
    <form method="POST">
        <label>Nombre completo:</label>
        <input type="text" name="nombre" required>

        <label>Usuario:</label>
        <input type="text" name="usuario" required>

        <label>Contraseña:</label>
        <input type="password" name="clave" required>

        <label>Rol:</label>
        <select name="rol">
            <option value="organizador">Organizador</option>
            <option value="admin">Administrador</option>
        </select>

        <button type="submit">➕ Crear Usuario</button>
        <a href="login_evento.php" class="boton-volver">⬅ Volver al Login</a>
    </form>
</div>
</body>
</html>
