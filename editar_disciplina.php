<?php
include 'conexion.php';
session_start();
if (!isset($_SESSION['gimnasio_id'])) die("Acceso denegado.");

if (!isset($_GET['id'])) die("ID no válido");
$id = $_GET['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"]);
    $stmt = $conexion->prepare("UPDATE disciplinas SET nombre=? WHERE id=?");
    $stmt->bind_param("si", $nombre, $id);
    $stmt->execute();
    echo "<script>alert('Disciplina actualizada'); window.location.href='disciplinas.php';</script>";
} else {
    $result = $conexion->query("SELECT nombre FROM disciplinas WHERE id=$id");
    $row = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Editar Disciplina</title>
<style>body{background:#111;color:#FFD700;font-family:sans-serif;padding:20px}input{padding:8px;width:300px}button{padding:10px;background:#FFD700;border:none}</style>
</head><body>
<h2>Editar Disciplina</h2>
<form method="post">
    <label>Nombre:</label><br>
    <input type="text" name="nombre" value="<?= htmlspecialchars($row['nombre']) ?>" required><br><br>
    <button type="submit">Actualizar</button>
</form>
</body></html>