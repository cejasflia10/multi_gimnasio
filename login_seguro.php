<?php
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

    // Cambiado a nombre_usuario y contrasena según la base de datos
    $consulta = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
    $stmt = $conexion->prepare($consulta);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $row = $resultado->fetch_assoc();

        if (password_verify($contrasena, $row['contrasena'])) {
            $_SESSION['usuario'] = $row['nombre_usuario'];
            $_SESSION['id_gimnasio'] = $row['id_gimnasio'];

            // Permisos específicos
            $_SESSION['puede_ver_clientes'] = $row['puede_ver_clientes'];
            $_SESSION['puede_ver_membresias'] = $row['puede_ver_membresias'];
            $_SESSION['puede_ver_profesores'] = $row['puede_ver_profesores'];
            $_SESSION['puede_ver_ventas'] = $row['puede_ver_ventas'];
            $_SESSION['puede_ver_asistencias'] = $row['puede_ver_asistencias'];
            $_SESSION['puede_ver_panel'] = $row['puede_ver_panel'];
            $_SESSION['puede_ver_admin'] = $row['puede_ver_admin'];

            // Redirige al panel
            header("Location: index.php");
            exit();
        } else {
            header("Location: login.php?error=1"); // Contraseña incorrecta
            exit();
        }
    } else {
        header("Location: login.php?error=1"); // Usuario no encontrado
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
