<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $clave = trim($_POST["clave"]);

    $stmt = $conexion->prepare("SELECT id, usuario, contrasena, rol, debe_cambiar_contrasena FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $row = $resultado->fetch_assoc();

        if (password_verify($clave, $row["contrasena"])) {
            $_SESSION["usuario_id"] = $row["id"];
            $_SESSION["usuario"] = $row["usuario"];
            $_SESSION["rol"] = $row["rol"];

            if ($row["debe_cambiar_contrasena"]) {
                header("Location: cambiar_contrasena.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }
    $error = "Usuario o contraseña incorrectos";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login - Fight Academy</title>
  <style>
    body { background-color: #111; color: white; font-family: Arial; text-align: center; padding-top: 100px; }
    form { background: #222; padding: 20px; display: inline-block; border-radius: 10px; }
    input { display: block; margin: 10px auto; padding: 10px; background: #333; color: white; border: none; border-radius: 5px; width: 200px; }
    button { padding: 10px 20px; background: gold; border: none; font-weight: bold; border-radius: 5px; }
    h2 { color: gold; }
  </style>
</head>
<body>
  <h2>Login - Fight Academy</h2>
  <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
  <form method="post">
    <input type="text" name="usuario" placeholder="Usuario" required>
    <input type="password" name="clave" placeholder="Contraseña" required>
    <button type="submit">Ingresar</button>
  </form>
</body>
</html>
