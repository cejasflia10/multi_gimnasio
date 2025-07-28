<?php
session_start();
include 'conexion.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Normalizamos el usuario ingresado
    $usuario = strtolower(trim($_POST['usuario'] ?? ''));
    $clave = trim($_POST['clave'] ?? '');

    if ($usuario && $clave) {
        $stmt = $conexion->prepare("SELECT id, nombre, clave, rol, usuario FROM usuarios_eventos WHERE LOWER(usuario) = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $datos = $res->fetch_assoc();

            // Verificamos clave (encriptada o en texto plano)
            if ($clave === $datos['clave'] || password_verify($clave, $datos['clave'])) {

                // Guardamos todos los datos normalizados
                $_SESSION['evento_usuario_id'] = $datos['id'];
                $_SESSION['evento_usuario_nombre'] = $datos['nombre'];
                $_SESSION['evento_usuario_rol'] = $datos['rol'];

                // ✅ Guardamos el usuario en minúsculas sin espacios
                $_SESSION['usuario'] = strtolower(trim($datos['usuario']));

                header("Location: panel_eventos.php");
                exit;
            } else {
                $mensaje = "❌ Contraseña incorrecta.";
            }
        } else {
            $mensaje = "❌ Usuario no encontrado.";
        }
    } else {
        $mensaje = "⚠️ Ingresá usuario y contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Panel de Eventos</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body style="background: black; color: gold;">
    <div class="contenedor" style="max-width: 400px; margin-top: 60px;">
        <h2>🎯 Acceso Panel de Eventos</h2>
        <?php if ($mensaje) echo "<p style='color: red;'>$mensaje</p>"; ?>
        <form method="POST">
            <label>Usuario:</label>
            <input type="text" name="usuario" required>

            <label>Contraseña:</label>
            <input type="password" name="clave" required>

            <button type="submit">🔐 Ingresar</button>
        </form>
        <a href="index.php" class="boton-volver">⬅ Volver</a>
    </div>
</body>
</html>
