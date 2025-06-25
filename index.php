<?php
session_start();
include 'menu.php';
include 'conexion.php';
include 'funciones.php';

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$usuario = $_SESSION['usuario'] ?? 'Usuario';

// Obtener nombre del gimnasio
$gimnasio_nombre = '';
$res_gim = $conexion->query("SELECT nombre FROM gimnasios WHERE id = $gimnasio_id");
if ($res_gim && $fila = $res_gim->fetch_assoc()) {
    $gimnasio_nombre = $fila['nombre'];
}

// PAGOS del día y mes (de tabla membresías)
$pagos_dia = $conexion->query("SELECT SUM(total) AS total FROM membresias WHERE id_gimnasio = $gimnasio_id AND DATE(fecha_inicio) = CURDATE()");
$pagos_mes = $conexion->query("SELECT SUM(total) AS total FROM membresias WHERE id_gimnasio = $gimnasio_id AND MONTH(fecha_inicio) = MONTH(CURDATE())");

$total_pagos_dia = $pagos_dia->fetch_assoc()['total'] ?? 0;
$total_pagos_mes = $pagos_mes->fetch_assoc()['total'] ?? 0;

// VENTAS del mes unificadas
$total_ventas_mes = 0;
foreach (['ventas_protecciones', 'ventas_indumentaria', 'ventas_suplementos'] as $tabla) {
    $res = $conexion->query("SELECT SUM(total) AS total FROM $tabla WHERE id_gimnasio = $gimnasio_id AND MONTH(fecha) = MONTH(CURDATE())");
    $total_ventas_mes += $res->fetch_assoc()['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #111;
      color: gold;
    }
    .contenido {
      margin-left: 260px;
      padding: 20px;
    }
    h1, h2 {
      text-align: center;
      margin-bottom: 10px;
    }
    .tarjetas {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      justify-content: center;
      margin-bottom: 30px;
    }
    .tarjeta {
      background: #222;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
      width: 220px;
    }
    .tarjeta h3 {
      margin: 0 0 10px;
    }

    @media (max-width: 768px) {
      .contenido {
        margin-left: 0;
        padding: 10px;
      }
    }
  </style>
</head>
<body>

<div class="contenido">
  <h1>Bienvenido, <?= htmlspecialchars($usuario) ?></h1>
  <h2><?= htmlspecialchars($gimnasio_nombre) ?></h2>

  <div class="tarjetas">
    <div class="tarjeta">
      <h3>Pagos del Día</h3>
      <p>$<?= number_format($total_pagos_dia, 0, ',', '.') ?></p>
    </div>
    <div class="tarjeta">
      <h3>Pagos del Mes</h3>
      <p>$<?= number_format($total_pagos_mes, 0, ',', '.') ?></p>
    </div>
    <div class="tarjeta">
      <h3>Ventas del Mes</h3>
      <p>$<?= number_format($total_ventas_mes, 0, ',', '.') ?></p>
    </div>
  </div>
</div>

</body>
</html>
