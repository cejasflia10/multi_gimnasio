<?php
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID no especificado.");
}

$id = $_GET['id'];

$stmt = $conexion->prepare("DELETE FROM turnos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: turnos_profesor.php");
exit;
?>
