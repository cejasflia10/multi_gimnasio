<?php
include 'conexion.php';
session_start();
if (!isset($_SESSION['gimnasio_id'])) die("Acceso denegado.");
$gimnasio_id = $_SESSION['gimnasio_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"]);
    $stmt = $conexion->prepare("INSERT INTO disciplinas (nombre, gimnasio_id) VALUES (?, ?)");
    $stmt->bind_param("si", $nombre, $gimnasio_id);
    $stmt->execute();
    echo "<script>alert('Disciplina agregada'); window.location.href='disciplinas.php';</script>";
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Crear Disciplina</title>
<style>body{background:#111;color:#FFD700;font-family:sans-serif;padding:20px}input{padding:8px;width:300px}button{padding:10px;background:#FFD700;border:none}</style>
</head><body>
<h2>Nueva Disciplina</h2>
<form method="post">
    <label>Nombre:</label><br><input type="text" name="nombre" required><br><br>
    <button type="submit">Guardar</button>
</form>
</body></html>