<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include "conexion.php";
    $usuario = $_POST['usuario'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';
    $rol = $_POST['rol'] ?? 'admin';

    if ($usuario && $contrasena) {
        $contrasena_hash = hash('sha256', $contrasena);
        $verificar = $conexion->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
        $verificar->bind_param("s", $usuario);
        $verificar->execute();
        $resultado = $verificar->get_result();

        if ($resultado->num_rows > 0) {
            echo "El usuario ya existe.";
        } else {
            $sql = "INSERT INTO usuarios (nombre_usuario, contrasena, rol) VALUES (?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("sss", $usuario, $contrasena_hash, $rol);
            if ($stmt->execute()) {
                echo "Usuario creado correctamente.";
            } else {
                echo "Error al crear el usuario: " . $stmt->error;
            }
        }
    } else {
        echo "Debe completar todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Admin</title>
</head>
<body>
    <h2>Crear Usuario Admin</h2>
    <form method="POST">
        <input type="text" name="usuario" placeholder="Usuario" required><br>
        <input type="password" name="contrasena" placeholder="Contraseña" required><br>
        <select name="rol">
            <option value="admin">Admin</option>
            <option value="profesor">Profesor</option>
            <option value="instructor">Instructor</option>
        </select><br>
        <button type="submit">Crear Usuario</button>
    </form>
</body>
</html>
