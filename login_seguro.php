<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'] ?? null;
    $contrasena = $_POST['contrasena'] ?? null;

    if (!$usuario || !$contrasena) {
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

        if (password_verify($contrasena, $row['contrasena'])) {
            $_SESSION['usuario'] = $row['nombre_usuario'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['id_gimnasio'] = $row['id_gimnasio'];
            header("Location: index.php");
            exit();
        } else {
            header("Location: login.php?error=2");
            exit();
        }
    } else {
        header("Location: login.php?error=3");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>
