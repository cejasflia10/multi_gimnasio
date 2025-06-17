<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    die("Acceso no autorizado.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nueva = trim($_POST["nueva"]);
    $confirmar = trim($_POST["confirmar"]);

    if ($nueva === $confirmar && strlen($nueva) >= 6) {
        $hash = password_hash($nueva, PASSWORD_BCRYPT);
        $id = $_SESSION['usuario_id'];

        $stmt = $conexion->prepare("UPDATE usuarios SET contrasena=?, debe_cambiar_contrasena=0 WHERE id=?");
        $stmt->bind_param("si", $hash, $id);
        $stmt->execute();

        echo "<script>alert('Contraseña actualizada'); window.location.href='index.php';</script>";
        exit;
    } else {
        $error = "Las contraseñas no coinciden o son demasiado cortas.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cambiar Contraseña</title>
  <style>
    body { background-color: #111; color: white; font-family: Arial; text-align: center; padding: 50px; }
    form { background: #222; padding: 20px; border-radius: 10px; display: inline-block; }
    input { padding: 10px; margin: 10px; background: #333; color: white; border: none; border-radius: 5px; }
    button { padding: 10px 20px; background: gold; border: none; font-weight: bold; border-radius: 5px; }
  </style>
</head>
<body>
  <h2>Cambiar Contraseña</h2>
  <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
  <form method="post">
    <input type="password" name="nueva" placeholder="Nueva contraseña" required><br>
    <input type="password" name="confirmar" placeholder="Confirmar contraseña" required><br>
    <button type="submit">Guardar</button>
  </form>
</body>
</html>
