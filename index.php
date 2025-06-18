<?php
session_start();

if (!isset($_SESSION["gimnasio_id"])) {
    die("⚠️ No has iniciado sesión correctamente.");
}

$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';

// Variables base
$pagos_dia = 0;
$pagos_mes = 0;
$ventas_dia = 0;
$ventas_mes = 0;
$cumples = [];
$vencimientos = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <style>
        body { background: #111; color: #FFD700; font-family: Arial; padding: 20px; }
        h1 { text-align: center; }
        .tarjeta { background: #222; padding: 15px; margin: 10px auto; border-radius: 10px; width: 80%; }
    </style>
</head>
<body>
    <h1>Bienvenido al Panel</h1>
    <div class="tarjeta">Pagos del día: $<?= $pagos_dia ?></div>
    <div class="tarjeta">Pagos del mes: $<?= $pagos_mes ?></div>
    <div class="tarjeta">Ventas del día: $<?= $ventas_dia ?></div>
    <div class="tarjeta">Ventas del mes: $<?= $ventas_mes ?></div>
</body>
</html>
