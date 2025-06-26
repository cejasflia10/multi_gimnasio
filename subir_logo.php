<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Validar usuario logueado
$id_usuario = $_SESSION['id'] ?? 0;
if ($id_usuario === 0) {
    die("Acceso no autorizado.");
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    $archivo = $_FILES['logo'];

    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $nombre_temporal = $archivo['tmp_name'];
        $nombre_archivo = 'logos/' . uniqid('logo_') . '_' . basename($archivo['name']);

        // Crear carpeta si no existe
        if (!is_dir('logos')) {
            mkdir('logos', 0777, true);
        }

        if (move_uploaded_file($nombre_temporal, $nombre_archivo)) {
            $conexion->query("UPDATE usuarios SET logo = '$nombre_archivo' WHERE id = $id_usuario");
            $mensaje = "Logo actualizado correctamente.";
        } else {
            $mensaje = "Error al subir el archivo.";
        }
    } else {
        $mensaje = "Error en la subida del archivo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Logo</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 30px;
            text-align: center;
        }
        input[type="file"], input[type="submit"] {
            margin: 15px;
            padding: 10px;
        }
    </style>
</head>
<body>
    <h2>Subir Logo del Usuario</h2>

    <?php if ($mensaje): ?>
        <p><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="logo" accept="image/*" required><br>
        <input type="submit" value="Subir Logo">
    </form>

    <a href="index.php" style="color: gold;">Volver al panel</a>
</body>
</html>
