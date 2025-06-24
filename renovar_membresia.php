<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID de membresía no proporcionado.");
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$membresia = $conexion->query("SELECT * FROM membresias WHERE id = $id AND gimnasio_id = $gimnasio_id LIMIT 1");

if (!$membresia || $membresia->num_rows === 0) {
    die("Membresía no encontrada.");
}

$membresia = $membresia->fetch_assoc();

$cliente_id = intval($membresia['cliente_id']);
$cliente = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE id = $cliente_id LIMIT 1");

if (!$cliente || $cliente->num_rows === 0) {
    die("Cliente no encontrado.");
}

$cliente = $cliente->fetch_assoc();
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Renovar Membresía</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
    label { display: block; margin-top: 10px; font-weight: bold; }
    input, select, button { width: 100%; padding: 10px; margin-top: 5px; background-color: #222; color: gold; border: 1px solid gold; border-radius: 6px; }
    h1 { text-align: center; }
    button { background-color: gold; color: black; font-weight: bold; cursor: pointer; margin-top: 20px; }
  </style>
</head>
<body>
<h1>Renovar Membresía</h1>
<form action="guardar_renovacion.php" method="POST">
  <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">

  <label>Cliente:</label>
  <input type="text" value="<?= $cliente['apellido'] . ', ' . $cliente['nombre'] . ' (' . $cliente['dni'] . ')' ?>" readonly>

  <label>Nuevo Plan:</label>
  <select name="plan_id" id="plan_id" onchange="cargarDatosPlan(this.value)" required>