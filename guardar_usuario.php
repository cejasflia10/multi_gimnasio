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

    $permiso_clientes = isset($_POST['permiso_clientes']) ? 1 : 0;
    $permiso_membresias = isset($_POST['permiso_membresias']) ? 1 : 0;
    $permiso_ventas = isset($_POST['permiso_ventas']) ? 1 : 0;
    $permiso_profesores = isset($_POST['permiso_profesores']) ? 1 : 0;
    $permiso_panel = isset($_POST['permiso_panel']) ? 1 : 0;
    $permiso_asistencias = isset($_POST['permiso_asistencias']) ? 1 : 0;

    $verifica = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $verifica->bind_param("s", $usuario);
    $verifica->execute();
    $verifica->store_result();

    if ($verifica->num_rows > 0) {
        echo "<script>alert('El usuario ya existe'); window.location.href='usuarios.php';</script>";
    } else {
        $stmt = $conexion->prepare("INSERT INTO usuarios 
            (usuario, clave, rol, gimnasio_id, 
             permiso_clientes, permiso_membresias, permiso_ventas, permiso_profesores, permiso_panel, permiso_asistencias) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiiiiiii", $usuario, $clave, $rol, $gimnasio_id,
            $permiso_clientes, $permiso_membresias, $permiso_ventas, $permiso_profesores, $permiso_panel, $permiso_asistencias);

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
