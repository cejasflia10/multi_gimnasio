<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$min = intval($_POST['nuevo_min'] ?? 0);
$max = intval($_POST['nuevo_max'] ?? 0);
$precio = floatval($_POST['nuevo_precio'] ?? 0);

$conexion->query("INSERT INTO precio_hora (gimnasio_id, rango_min, rango_max, precio) VALUES ($gimnasio_id, $min, $max, $precio)");

header("Location: configurar_precios_hora.php");
