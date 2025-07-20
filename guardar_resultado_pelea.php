<?php
include 'conexion.php';
session_start();

$rojo_id = intval($_POST['rojo_id']);
$azul_id = intval($_POST['azul_id']);
$ganador = $_POST['ganador'];

$conexion->query("INSERT INTO peleas_evento (rojo_id, azul_id, ganador) VALUES ($rojo_id, $azul_id, '$ganador')");

header("Location: ver_peleas_evento.php");
exit;
?>
