<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $gimnasio_id = $_SESSION['gimnasio_id'];

    $stmt = $conexion->prepare("INSERT INTO disciplinas (nombre, descripcion, gimnasio_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nombre, $descripcion, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Disciplina creada correctamente.'); window.location.href='disciplinas.php';</script>";
    } else {
        echo "Error al crear disciplina: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
}
?>
