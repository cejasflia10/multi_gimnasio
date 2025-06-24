<?php
include "conexion.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nombre = $_POST['nombre'] ?? '';
$precio = $_POST['precio'] ?? 0;
$dias_disponibles = $_POST['dias_disponibles'] ?? 0;
$duracion = $_POST['duracion'] ?? 1;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$id = $_POST['id'] ?? null;

if ($id) {
    $query = "UPDATE planes SET nombre='$nombre', precio=$precio, dias_disponibles=$dias_disponibles, duracion=$duracion WHERE id=$id";
} else {
    $query = "INSERT INTO planes (nombre, precio, dias_disponibles, duracion, gimnasio_id)
              VALUES ('$nombre', $precio, $dias_disponibles, $duracion, $gimnasio_id)";
}

$conexion->query($query);
header("Location: planes.php");
exit;