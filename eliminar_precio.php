<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = intval($_POST['id'] ?? 0);

$conexion->query("DELETE FROM precio_hora WHERE id = $id AND gimnasio_id = $gimnasio_id");

header("Location: configurar_precios_hora.php");
