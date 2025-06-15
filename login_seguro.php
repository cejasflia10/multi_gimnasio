<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "conexion.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';

    if (empty($usuario) || empty($contrasena)) {
        header("Location: login.php?error=1");
        exit();
    }

    $consulta = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
    $stmt = $conexion->prepare($consulta);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $row = $resultado->fetch_assoc();
        $contrasena_sha256 = hash('sha256', $contrasena);
        if ($contrasena_sha256 === $row['contrasena']) {
            $_SESSION['usuario'] = $row['nombre_usuario'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['id_gimnasio'] = $row['id_gimnasio'] ?? null;
            header("Location: index.php");
        } else {
            header("Location: login.php?error=2");
        }
    } else {
        header("Location: login.php?error=3");
    }
}
?>
