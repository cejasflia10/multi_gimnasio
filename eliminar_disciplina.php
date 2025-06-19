<?php
include 'conexion.php';
session_start();
if (!isset($_SESSION['gimnasio_id'])) die("Acceso denegado.");

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $conexion->query("DELETE FROM disciplinas WHERE id = $id");
    echo "<script>alert('Disciplina eliminada'); window.location.href='disciplinas.php';</script>";
} else {
    echo "ID no válido.";
}
?>