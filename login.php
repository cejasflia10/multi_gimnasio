<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $clave = trim($_POST["clave"]);

    $stmt = $conexion->prepare("SELECT id, usuario, contrasena, rol, debe_cambiar_contrasena, id_gimnasio FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $row = $resultado->fetch_assoc();

        // Acepta contraseña encriptada o en texto plano
        if (password_verify($clave, $row["contrasena"]) || $clave === $row["contrasena"]) {
            $_SESSION["usuario_id"] = $row["id"];
            $_SESSION["usuario"] = $row["usuario"];
            $_SESSION["rol"] = $row["rol"];
            $_SESSION["gimnasio_id"] = $row["id_gimnasio"];

            if ($row["debe_cambiar_contrasena"] == 1) {
                header("Location: cambiar_contrasena.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }

    // Si falla
    echo "<script>alert('Usuario o contraseña incorrectos'); window.location.href='login.php';</script>";
}
?>
