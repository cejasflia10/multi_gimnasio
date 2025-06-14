<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    $logo = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $nombreArchivo = basename($_FILES['logo']['name']);
        $rutaDestino = 'logos/' . $nombreArchivo;
        move_uploaded_file($_FILES['logo']['tmp_name'], $rutaDestino);
        $logo = $rutaDestino;
    }

    $query = "INSERT INTO gimnasios (nombre, direccion, telefono, email, logo)
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("sssss", $nombre, $direccion, $telefono, $email, $logo);
    $stmt->execute();

    header("Location: gimnasios.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Gimnasio</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="email"], input[type="file"] {
            width: 100%; padding: 8px; margin-top: 5px; border: none; border-radius: 4px;
        }
        .btn { margin-top: 15px; padding: 10px 20px; background: #ffc107; color: #111; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #e0a800; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Agregar Nuevo Gimnasio</h1>
    <form action="" method="POST" enctype="multipart/form-data">
        <label for="nombre">Nombre del gimnasio:</label>
        <input type="text" name="nombre" required>

        <label for="direccion">Dirección:</label>
        <input type="text" name="direccion">

        <label for="telefono">Teléfono:</label>
        <input type="text" name="telefono">

        <label for="email">Email:</label>
        <input type="email" name="email">

        <label for="logo">Logo (opcional):</label>
        <input type="file" name="logo">

        <button type="submit" class="btn">Guardar Gimnasio</button>
    </form>
</div>
</body>
</html>
