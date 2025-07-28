<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$mensaje = "";
include 'menu_evento.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $dni = trim($_POST['dni']);
    $clave = trim($_POST['clave']);

    if ($nombre && $apellido && $dni && $clave) {
        $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
        $conexion->query("INSERT INTO jueces_evento (nombre, apellido, dni, clave) VALUES ('$nombre', '$apellido', '$dni', '$clave_hash')");
        $mensaje = "✅ Juez registrado correctamente.";
    } else {
        $mensaje = "❌ Complete todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Juez</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>🧑‍⚖️ Registrar Nuevo Juez</h2>

    <?php if ($mensaje): ?>
        <p><?= $mensaje ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Nombre:</label>
        <input type="text" name="nombre" required>

        <label>Apellido:</label>
        <input type="text" name="apellido" required>

        <label>DNI:</label>
        <input type="text" name="dni" required>

        <label>Contraseña:</label>
        <input type="password" name="clave" required>

        <button type="submit">💾 Registrar Juez</button>
    </form>
</div>
</body>
</html>
