<?php
session_start();
if (!isset($_SESSION["gimnasio_id"])) {
    die("⚠️ No has iniciado sesión correctamente.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';

$pagos_dia = 0;
$pagos_mes = 0;
$ventas_dia = 0;
$ventas_mes = 0;

// Cumples próximos (simulado)
$cumples = [
    ["nombre" => "Juan Pérez", "fecha" => "2025-06-20"],
    ["nombre" => "Ana García", "fecha" => "2025-06-21"]
];

// Vencimientos próximos (simulado)
$vencimientos = [
    ["nombre" => "Carlos Ruiz", "vence" => "2025-06-22"],
    ["nombre" => "Lucía Gómez", "vence" => "2025-06-24"]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="contenido">
        <h1>Bienvenido al Panel</h1>
        <div class="tarjeta">Pagos del día: $<?= $pagos_dia ?></div>
        <div class="tarjeta">Pagos del mes: $<?= $pagos_mes ?></div>
        <div class="tarjeta">Ventas del día: $<?= $ventas_dia ?></div>
        <div class="tarjeta">Ventas del mes: $<?= $ventas_mes ?></div>

        <h2 class="titulo-sec">🎂 Próximos Cumpleaños</h2>
        <ul class="lista">
            <?php foreach ($cumples as $c): ?>
                <li><?= $c["nombre"] ?> - <?= $c["fecha"] ?></li>
            <?php endforeach; ?>
        </ul>

        <h2 class="titulo-sec">📅 Próximos Vencimientos</h2>
        <ul class="lista">
            <?php foreach ($vencimientos as $v): ?>
                <li><?= $v["nombre"] ?> - Vence: <?= $v["vence"] ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</body>
</html>
