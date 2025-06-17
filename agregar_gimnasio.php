<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);

    $stmt = $conexion->prepare("INSERT INTO gimnasios (nombre, direccion, telefono, email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nombre, $direccion, $telefono, $email);

    if ($stmt->execute()) {
        echo "Gimnasio agregado correctamente.";
        // Opcionalmente podés redirigir:
        // header("Location: gimnasios.php");
        // exit();
    } else {
        echo "Error al agregar gimnasio: " . $stmt->error;
    }

    $stmt->close();
}
?>
