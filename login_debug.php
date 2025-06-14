<?php
session_start();
include 'conexion.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "Formulario recibido.<br>";

    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    echo "Buscando usuario: $usuario<br>";

    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        echo "Usuario encontrado.<br>";
        $fila = $res->fetch_assoc();

        if ($contrasena === $fila['contrasena']) {
            echo "Contraseña correcta.<br>";

            $_SESSION['id_gimnasio'] = $fila['id_gimnasio'];
            echo "ID gimnasio guardado en sesión: " . $_SESSION['id_gimnasio'] . "<br>";

            echo "Redirigiendo a index.php...<br>";
            header("Location: index.php");
            exit;

        } else {
            echo "Contraseña incorrecta.<br>";
        }
    } else {
        echo "Usuario no encontrado.<br>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login Debug</title>
</head>
<body style="background:#111; color:#fff; font-family:Arial; padding:40px;">
    <h2>Login (Debug)</h2>
    <form method="post">
        Usuario:<br>
        <input type="text" name="usuario" required><br><br>
        Contraseña:<br>
        <input type="password" name="contrasena" required><br><br>
        <input type="submit" value="Ingresar">
    </form>
</body>
</html>
