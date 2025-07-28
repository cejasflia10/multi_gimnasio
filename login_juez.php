<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

$id_juez = $_SESSION['juez_id'] ?? 0;

if (!$id_juez) {
    header("Location: login_juez.php");
    exit;
}

$sql = "SELECT * FROM jueces_evento WHERE id = $id_juez";
$resultado = $conexion->query($sql);

if (!$resultado || $resultado->num_rows === 0) {
    echo "Juez no encontrado.";
    exit;
}

$juez = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Juez - <?= htmlspecialchars($juez['nombre']) ?></title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
        <h2>Bienvenido, <?= htmlspecialchars($juez['nombre']) ?></h2>
        <p>Aquí va el contenido del panel de juez.</p>
        <a href="logout_juez.php">Cerrar sesión</a>
    </div>
</body>
</html>
