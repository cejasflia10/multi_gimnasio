<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['id'])) {
    die("ID del profesor no especificado.");
}

$id = intval($_GET['id']);
$resultado = $conexion->query("SELECT * FROM profesores WHERE id = $id");

if ($resultado->num_rows === 0) {
    die("Profesor no encontrado.");
}

$profesor = $resultado->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $dni = $_POST['dni'];
    $domicilio = $_POST['domicilio'];
    $telefono = $_POST['telefono'];

    $stmt = $conexion->prepare("UPDATE profesores SET apellido=?, nombre=?, dni=?, domicilio=?, telefono=? WHERE id=?");
    $stmt->bind_param("sssssi", $apellido, $nombre, $dni, $domicilio, $telefono, $id);
    $stmt->execute();

    echo "<script>alert('Profesor actualizado correctamente'); window.location.href='ver_profesores.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>✏️ Editar Datos del Profesor</h2>
    <form method="POST">
        <label for="apellido">Apellido:</label>
        <input type="text" name="apellido" value="<?= htmlspecialchars($profesor['apellido']) ?>" required>

        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($profesor['nombre']) ?>" required>

        <label for="dni">DNI:</label>
        <input type="number" name="dni" value="<?= htmlspecialchars($profesor['dni']) ?>" required>

        <label for="domicilio">Domicilio:</label>
        <input type="text" name="domicilio" value="<?= htmlspecialchars($profesor['domicilio']) ?>">

        <label for="telefono">Teléfono:</label>
        <input type="tel" name="telefono" value="<?= htmlspecialchars($profesor['telefono']) ?>">

        <button type="submit">Guardar Cambios</button>
    </form>

    <br>
    <a href="ver_profesores.php" style="color:#ffd600;">⬅ Volver al listado</a>
</div>
</body>
</html>
