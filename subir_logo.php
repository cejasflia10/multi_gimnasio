<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$id_usuario = $_SESSION['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    $nombre = $_FILES['logo']['name'];
    $tmp = $_FILES['logo']['tmp_name'];
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

    if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
        $carpeta = "logos/";
        if (!file_exists($carpeta)) mkdir($carpeta);
        
        $nombre_archivo = "logo_usuario_" . $id_usuario . "." . $ext;
        $destino = $carpeta . $nombre_archivo;

        move_uploaded_file($tmp, $destino);

        // Guardar en la BD
        $conexion->query("UPDATE usuarios SET logo = '$destino' WHERE id = $id_usuario");

        header("Location: index.php");
        exit;
    } else {
        echo "<p style='color:red'>Solo se permiten archivos JPG o PNG.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Logo</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; text-align: center; }
        input[type="file"] { margin: 20px 0; }
        input[type="submit"] {
            background: gold; color: black; padding: 10px 20px;
            border: none; font-weight: bold; border-radius: 5px;
        }
        input[type="submit"]:hover {
            background: #ffd700;
        }
    </style>
</head>
<body>

<h1>📤 Subir Logo Personalizado</h1>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="logo" accept=".jpg,.jpeg,.png" required>
    <br>
    <input type="submit" value="Subir Logo">
</form>

</body>
</html>
