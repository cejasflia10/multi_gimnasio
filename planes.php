<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// Insertar nuevo plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $precio = $_POST['precio'];
    $dias_disponibles = $_POST['dias_disponibles'];
    $duracion_meses = $_POST['duracion_meses'];

    $stmt = $conexion->prepare("INSERT INTO planes (nombre, precio, dias_disponibles, duracion_meses, gimnasio_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sddii", $nombre, $precio, $dias_disponibles, $duracion_meses, $gimnasio_id);
    $stmt->execute();
}

// Eliminar plan
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $conexion->prepare("DELETE FROM planes WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param("ii", $id, $gimnasio_id);
    $stmt->execute();
}

$planes = [];
$resultado = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
while ($row = $resultado->fetch_assoc()) {
    $planes[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background-color: #111; color: gold; font-family: Arial, sans-serif;">
    <div style="padding: 20px;">
        <h1>Planes</h1>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre del plan" required>
            <input type="number" name="precio" step="0.01" placeholder="Precio" required>
            <input type="number" name="dias_disponibles" placeholder="Días disponibles" required>
            <input type="number" name="duracion_meses" placeholder="Duración en meses" required>
            <button type="submit">Agregar Plan</button>
        </form>
        <table border="1" cellpadding="10" cellspacing="0" style="margin-top: 20px; color: white; width: 100%;">
            <thead style="background-color: #222;">
                <tr>
                    <th>Nombre</th>
                    <th>Precio</th>
                    <th>Días disponibles</th>
                    <th>Duración (meses)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($planes as $plan): ?>
                <tr>
                    <td><?= htmlspecialchars($plan['nombre']) ?></td>
                    <td>$<?= number_format($plan['precio'], 2) ?></td>
                    <td><?= $plan['dias_disponibles'] ?></td>
                    <td><?= $plan['duracion_meses'] ?></td>
                    <td><a href="?eliminar=<?= $plan['id'] ?>" onclick="return confirm('¿Eliminar este plan?')">Eliminar</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
