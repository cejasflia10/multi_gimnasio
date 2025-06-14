<?php
include 'conexion.php';

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $domicilio = $_POST['domicilio'];
    $rfid = $_POST['rfid'];

    $stmt = $conexion->prepare("INSERT INTO profesores (apellido, nombre, telefono, domicilio, rfid) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $apellido, $nombre, $telefono, $domicilio, $rfid);
    if ($stmt->execute()) {
        $mensaje = "Profesor registrado correctamente.";
    } else {
        $mensaje = "Error al registrar.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Profesor</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        label { display: block; margin-top: 10px; }
        input { width: 100%; padding: 8px; margin-top: 5px; border-radius: 4px; border: none; }
        .btn { margin-top: 15px; padding: 10px 20px; background: #ffc107; color: #111; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #e0a800; }
        .msg { margin-top: 15px; color: #0f0; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Agregar Profesor</h1>
    <?php if ($mensaje): ?>
        <p class="msg"><?= $mensaje ?></p>
    <?php endif; ?>
    <form method="POST">
        <label>Apellido:</label>
        <input type="text" name="apellido" required>

        <label>Nombre:</label>
        <input type="text" name="nombre" required>

        <label>Teléfono:</label>
        <input type="text" name="telefono" required>

        <label>Domicilio:</label>
        <input type="text" name="domicilio" required>

        <label>RFID:</label>
        <input type="text" name="rfid" required>

        <button type="submit" class="btn">Registrar Profesor</button>
    </form>
</div>
</body>
</html>
