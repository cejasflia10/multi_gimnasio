<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $usuario = trim($_POST['usuario']);
    $email = trim($_POST['email']);
    $rol = $_POST['rol'];
    $id_gimnasio = intval($_POST['id_gimnasio']);

    if ($usuario === '' || $rol === '' || $id_gimnasio <= 0) {
        die("Datos incompletos.");
    }

    $stmt = $conexion->prepare("UPDATE usuarios SET usuario = ?, email = ?, rol = ?, id_gimnasio = ? WHERE id = ?");
    $stmt->bind_param("sssii", $usuario, $email, $rol, $id_gimnasio, $id);
    
    if ($stmt->execute()) {
        header("Location: ver_usuarios.php?mensaje=Usuario+actualizado");
        exit;
    } else {
        die("Error al actualizar: " . $stmt->error);
    }
} else {
    die("Acceso no autorizado.");
}
