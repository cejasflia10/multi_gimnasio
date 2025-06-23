<?php
include 'conexion.php';

if (!isset($_GET['dni'])) {
    die("Acceso inválido.");
}

$dni = $_GET['dni'];
$cliente = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1")->fetch_assoc();

if (!$cliente) {
    die("Cliente no encontrado.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 40px;
        }
        .panel {
            background-color: #222;
            border-radius: 10px;
            padding: 30px;
            display: inline-block;
            max-width: 400px;
            width: 100%;
        }
        h2 {
            margin-top: 0;
        }
        a.btn {
            display: block;
            margin: 10px auto;
            padding: 10px 15px;
            background-color: gold;
            color: #111;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        a.btn:hover {
            background-color: #ffcc00;
        }
    </style>
</head>
<body>

<div class="panel">
    <h2>Bienvenido, <?= $cliente['nombre'] . ' ' . $cliente['apellido'] ?></h2>
    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
    <p><strong>Fecha de nacimiento:</strong> <?= $cliente['fecha_nacimiento'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>

    <a class="btn" href="reservar_turno.php?dni=<?= $cliente['dni'] ?>">📅 Reservar Turno</a>
    <a class="btn" href="ver_qr.php?dni=<?= $cliente['dni'] ?>">📲 Ver mi QR</a>
    <a class="btn" href="ver_asistencias.php?dni=<?= $cliente['dni'] ?>">📋 Ver Asistencias</a>
    <a class="btn" href="ver_pagos.php?dni=<?= $cliente['dni'] ?>">💳 Ver Pagos</a>
    <a class="btn" href="cliente_acceso.php">← Volver / Cerrar Sesión</a>
</div>

</body>
</html>
