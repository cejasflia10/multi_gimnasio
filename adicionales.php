<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["nombre"], $_POST["precio"])) {
    $nombre = $_POST["nombre"];
    $precio = $_POST["precio"];

    $stmt = $conexion->prepare("INSERT INTO planes_adicionales (nombre, precio, gimnasio_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sdi", $nombre, $precio, $gimnasio_id);
    $stmt->execute();
}

if (isset($_GET["eliminar"])) {
    $id = $_GET["eliminar"];
    $conexion->query("DELETE FROM planes_adicionales WHERE id = $id AND gimnasio_id = $gimnasio_id");
}

$resultado = $conexion->query("SELECT * FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">
    <title>Planes Adicionales</title>
    <link rel="stylesheet" href="estilo_unificado.css">

</head>

<body>
<div class="contenedor">
    <!-- Contenido aquí -->

<h1>Planes Adicionales</h1>

<form method="POST">
    <input type="text" name="nombre" placeholder="Nombre" required>
    <input type="number" name="precio" placeholder="Precio" required step="0.01">
    <button type="submit">Agregar Adicional</button>
</form>

<table>
    <tr>
        <th>Nombre</th>
        <th>Precio</th>
        <th>Acciones</th>
    </tr>
    <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['nombre']) ?></td>
            <td>$<?= number_format($row['precio'], 2) ?></td>
            <td>
                <a class="btn-eliminar" href="?eliminar=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar este adicional?')">Eliminar</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

<a href="index.php" class="volver">Volver al menú</a>
</div>

</body>
</html>
