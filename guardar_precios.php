<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$ids = $_POST['id'] ?? [];
$rmin = $_POST['rango_min'] ?? [];
$rmax = $_POST['rango_max'] ?? [];
$precios = $_POST['precio'] ?? [];

foreach ($ids as $i => $id) {
    $id = intval($id);
    $min = intval($rmin[$i]);
    $max = intval($rmax[$i]);
    $precio = floatval($precios[$i]);

    $conexion->query("UPDATE precio_hora SET rango_min = $min, rango_max = $max, precio = $precio WHERE id = $id AND gimnasio_id = $gimnasio_id");
}

header("Location: configurar_precios_hora.php");
