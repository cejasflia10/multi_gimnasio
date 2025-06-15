<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - Multi Gimnasio</title>
</head>
<body style="background:#111; color:#f1f1f1; text-align:center; padding: 50px;">
    <h1>Bienvenido, <?php echo $_SESSION['usuario']; ?>!</h1>
    <p>Rol: <?php echo $_SESSION['rol']; ?></p>
    <p>ID Gimnasio: <?php echo $_SESSION['id_gimnasio']; ?></p>
    <a href="logout.php" style="color:#ffc107;">Cerrar sesión</a>
</body>
</html>
