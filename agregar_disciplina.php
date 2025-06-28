<?php
session_start();
include 'conexion.php';

$nombre = $_POST['nombre'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!empty($nombre) && $gimnasio_id > 0) {
    $nombre = $conexion->real_escape_string($nombre);

    $query = "INSERT INTO disciplinas (nombre, gimnasio_id) VALUES ('$nombre', $gimnasio_id)";
    
    if ($conexion->query($query)) {
        header("Location: disciplinas.php?ok=1");
        exit;
    } else {
        echo "❌ Error al guardar la disciplina: " . $conexion->error;
    }
} else {
    echo "⚠️ Nombre de disciplina o gimnasio inválido.";
}
?>
