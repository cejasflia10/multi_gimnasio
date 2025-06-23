<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $clases = intval($_POST['clases']);
    $duracion = intval($_POST['duracion_meses']);

    $stmt = $conexion->prepare("INSERT INTO plan_usuarios (nombre, precio, clases, duracion_meses, gimnasio_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdiii", $nombre, $precio, $clases, $duracion, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Plan guardado correctamente'); window.location.href='ver_planes.php';</script>";
    } else {
        echo "Error al guardar el plan: " . $stmt->error;
    }
    $stmt->close();
}
?>
