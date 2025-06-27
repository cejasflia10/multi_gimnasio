<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'permisos.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $nombre_usuario = $_POST['nombre_usuario'];
    $email = $_POST['email'];
    $rol = $_POST['rol'];
    $id_gimnasio = $_POST['id_gimnasio'];

    // Permisos
    $puede_ver_clientes = isset($_POST['puede_ver_clientes']) ? 1 : 0;
    $puede_ver_membresias = isset($_POST['puede_ver_membresias']) ? 1 : 0;
    $puede_ver_profesores = isset($_POST['puede_ver_profesores']) ? 1 : 0;
    $puede_ver_ventas = isset($_POST['puede_ver_ventas']) ? 1 : 0;
    $puede_ver_admin = isset($_POST['puede_ver_admin']) ? 1 : 0;
    $puede_ver_asistencias = isset($_POST['puede_ver_asistencias']) ? 1 : 0;
    $puede_ver_panel = isset($_POST['puede_ver_panel']) ? 1 : 0;

    $consulta = $conexion->prepare("UPDATE usuarios SET nombre_usuario=?, email=?, rol=?, id_gimnasio=?,
        puede_ver_clientes=?, puede_ver_membresias=?, puede_ver_profesores=?,
        puede_ver_ventas=?, puede_ver_admin=?, puede_ver_asistencias=?, puede_ver_panel=?
        WHERE id=?");
    $consulta->bind_param("sssiiiiiiiii", $nombre_usuario, $email, $rol, $id_gimnasio,
        $puede_ver_clientes, $puede_ver_membresias, $puede_ver_profesores,
        $puede_ver_ventas, $puede_ver_admin, $puede_ver_asistencias, $puede_ver_panel, $id);

    if ($consulta->execute()) {
        header("Location: usuarios_gimnasio.php?mensaje=actualizado");
    } else {
        echo "Error al actualizar: " . $conexion->error;
    }
}
?>
