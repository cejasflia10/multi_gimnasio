<?php
session_start();
if (!isset($_SESSION["gimnasio_id"])) {
    die("⛔ No has iniciado sesión correctamente.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';

// Datos de ejemplo para el panel
$pagos_dia = 0;
$pagos_mes = 0;
$ventas_dia = 0;
$ventas_mes = 0;
$cumples = ["Juan Pérez - 20/06", "Ana García - 22/06"];
$vencimientos = ["Pedro Gómez - 3 días", "Lucía Díaz - 7 días"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel - Fight Academy</title>
  <link rel="stylesheet" href="menu.css">
  <style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #111;
        color: #FFD700;
    }
    .contenido {
        margin-left: 260px;
        padding: 20px;
    }
    h1 {
        text-align: center;
        color: #FFD700;
    }
    .card {
        background: #222;
        padding: 15px;
        margin: 10px 0;
        border-left: 5px solid #FFD700;
        border-radius: 5px;
        font-size: 18px;
    }
    .card-group {
        max-width: 600px;
        margin: auto;
    }
    .titulo-seccion {
        margin-top: 40px;
        font-size: 20px;
        border-bottom: 1px solid #FFD700;
    }
    @media screen and (max-width: 768px) {
        .contenido {
            margin-left: 0;
            padding: 10px;
        }
        .card {
            font-size: 16px;
        }
    }
  </style>
</head>
<body>
  <?php include 'menu.php'; ?>
  <div class="contenido">
    <h1>Bienvenido al Panel</h1>
    <div class="card-group">
        <div class="card">Pagos del día: $<?= $pagos_dia ?></div>
        <div class="card">Pagos del mes: $<?= $pagos_mes ?></div>
        <div class="card">Ventas del día: $<?= $ventas_dia ?></div>
        <div class="card">Ventas del mes: $<?= $ventas_mes ?></div>
    </div>

    <div class="card-group">
        <div class="titulo-seccion">🎂 Próximos cumpleaños</div>
        <?php foreach ($cumples as $cumple): ?>
            <div class="card"><?= $cumple ?></div>
        <?php endforeach; ?>

        <div class="titulo-seccion">📅 Próximos vencimientos</div>
        <?php foreach ($vencimientos as $ven): ?>
            <div class="card"><?= $ven ?></div>
        <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
