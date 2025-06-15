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
    <title>Panel</title>
</head>
<body style="background-color: #111; color: white;">
    <h2>Bienvenido, <?php echo $_SESSION['usuario']; ?></h2>
    <p>Rol: <?php echo $_SESSION['rol']; ?></p>
    <p>ID Gimnasio: <?php echo $_SESSION['id_gimnasio']; ?></p>
    <a href="logout.php" style="color: orange;">Cerrar sesión</a>
</body>
</html>
