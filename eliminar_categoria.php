<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    if (!empty($nombre) && $gimnasio_id > 0) {
        $stmt = $conexion->prepare("INSERT INTO categorias (nombre, gimnasio_id) VALUES (?, ?)");
        $stmt->bind_param("si", $nombre, $gimnasio_id);
        $stmt->execute();
    }

    header("Location: ver_categorias.php");
    exit;
} else {
    echo "Acceso denegado";
}
?>
