<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $clave = password_hash(trim($_POST["clave"]), PASSWORD_BCRYPT);
    $rol = $_POST["rol"];
    $gimnasio_id = $_SESSION["gimnasio_id"];

    // Verificar si ya existe
    $verifica = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $verifica->bind_param("s", $usuario);
    $verifica->execute();
    $verifica->store_result();

    if ($verifica->num_rows > 0) {
        echo "<script>alert('El usuario ya existe'); window.location.href='usuarios.php';</script>";
    } else {
        $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, clave, rol, gimnasio_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $usuario, $clave, $rol, $gimnasio_id);

        if ($stmt->execute()) {
            echo "<script>alert('Usuario creado exitosamente'); window.location.href='usuarios.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }
    $verifica->close();
}
?>
