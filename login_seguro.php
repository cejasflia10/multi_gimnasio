<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    // Preparar la consulta
    $sql = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $row = $resultado->fetch_assoc();

        // ✅ Verificar contraseña segura
        if (password_verify($contrasena, $row['contrasena'])) {
            $_SESSION['usuario'] = $row['nombre_usuario'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['id_gimnasio'] = $row['id_gimnasio'];

            header("Location: index.php");
            exit();
        } else {
            header("Location: login.php?error=2"); // Contraseña incorrecta
            exit();
        }
    } else {
        header("Location: login.php?error=1"); // Usuario no encontrado
        exit();
    }

    $stmt->close();
    $conexion->close();
}
?>
