<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $clave = password_hash(trim($_POST["clave"]), PASSWORD_BCRYPT);
    $rol = $_POST["rol"];
    $gimnasio_id = $_SESSION["gimnasio_id"];

    $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, clave, rol, gimnasio_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $usuario, $clave, $rol, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Usuario creado exitosamente'); window.location.href='usuarios.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Usuario</title>
    <style>
        body {
            background-color: #111;
            color: #ffc107;
            font-family: Arial;
            text-align: center;
            padding-top: 60px;
        }
        input, select {
            margin: 10px;
            padding: 10px;
            width: 250px;
        }
        button {
            padding: 10px 20px;
            background-color: #ffc107;
            color: #000;
            border: none;
        }
    </style>
</head>
<body>
    <h2>Agregar Nuevo Usuario</h2>
    <form method="POST">
        <input type="text" name="usuario" placeholder="Nombre de usuario" required><br>
        <input type="password" name="clave" placeholder="Contraseña" required><br>
        <select name="rol" required>
            <option value="">Seleccionar rol</option>
            <option value="admin">Administrador</option>
            <option value="profesor">Profesor</option>
            <option value="instructor">Instructor</option>
        </select><br>
        <button type="submit">Crear Usuario</button>
    </form>
</body>
</html>
