<?php
session_start();
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST["usuario"];
    $clave = $_POST["clave"];

    $stmt = $conexion->prepare("SELECT id, contraseña, rol, id_gimnasio FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $usuario_data = $resultado->fetch_assoc();

        // Soporte para contraseña en texto plano o encriptada
        if ($clave === $usuario_data["contraseña"] || password_verify($clave, $usuario_data["contraseña"])) {
            $_SESSION["usuario_id"] = $usuario_data["id"];
            $_SESSION["rol"] = $usuario_data["rol"];
            $_SESSION["gimnasio_id"] = $usuario_data["id_gimnasio"];

            // Redireccionar según el rol
            header("Location: index.php");
            exit();
        } else {
            echo "<script>alert('Contraseña incorrecta'); window.location.href='login.php';</script>";
        }
    } else {
        echo "<script>alert('Usuario no encontrado'); window.location.href='login.php';</script>";
    }

    $stmt->close();
}
?>
