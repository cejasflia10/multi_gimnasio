<?php
include 'conexion.php';

$cliente_id = $_GET['cliente_id'] ?? null;
$gimnasio_id = $_GET['gimnasio_id'] ?? null;

if (!$cliente_id || !$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background-color: #000; color: gold; font-family: Arial; text-align: center; padding-top: 40px; }
        .info { margin: 20px auto; border: 2px solid gold; padding: 20px; max-width: 400px; border-radius: 10px; text-align: left; }
    </style>
</head>
<body>
    <h2>👋 Bienvenido <?= $cliente['apellido'] . ' ' . $cliente['nombre'] ?></h2>

    <div class="info">
        <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
        <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
        <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
        <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
    </div>
</body>
</html>
