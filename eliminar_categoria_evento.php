<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$id = $_GET['id'];
$conexion->query("DELETE FROM categorias_evento WHERE id = $id");

header("Location: ver_categorias_evento.php");
exit;
