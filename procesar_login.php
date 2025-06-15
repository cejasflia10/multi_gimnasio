<?php
session_start();
include 'conexion.php';

$usuario = $_POST['usuario'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';

$sql = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $row = $resultado->fetch_assoc();
    if ($contrasena === $row['contrasena']) {
        $_SESSION['usuario'] = $row['nombre_usuario'];
        $_SESSION['rol'] = $row['rol'];
        $_SESSION['id_gimnasio'] = $row['id_gimnasio'];
        header("Location: index.php");
    } else {
        header("Location: login.php?error=1");
    }
} else {
    header("Location: login.php?error=1");
}
?>
