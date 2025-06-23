<?php
session_start();
include 'conexion.php';

if (!isset($_GET['dni'])) {
    die("Acceso inválido.");
}

$dni = $_GET['dni'];

// Obtener cliente
$query = "SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Cliente no encontrado.");
}

$cliente = $resultado->fetch_assoc();

// Obtener membresía activa
$id_cliente = $cliente['id'];
$membresia = $conexion->query("SELECT * FROM membresias WHERE id_cliente = $id_cliente ORDER BY fecha_inicio DESC LIMIT 1")->fetch_assoc();

$clases = $membresia['clases_disponibles'] ?? 0;
$vencimiento = $membresia['fecha_vencimiento'] ?? 'No registrada';

// Obtener próximos turnos
$turnos = $conexion->query("SELECT * FROM turnos WHERE id_cliente = $id_cliente AND fecha >= CURDATE() ORDER BY fecha ASC LIMIT 5");
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
            padding: 20px;
        }
        .panel {
            background-color: #1a1a1a;
            padding: 20px;
            border-radius: 10px;
        }
        h1 {
            color: #f1f1f1;
        }
        .dato {
            margin-bottom: 10px;
        }
        .btn {
            background-color: gold;
            color: #111;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="panel">
        <h1>Bienvenido, <?= $cliente['nombre'] . " " . $cliente['apellido'] ?></h1>

        <div class="dato"><strong>DNI:</strong> <?= $cliente['dni'] ?></div>
        <div class="dato"><strong>Clases disponibles:</strong> <?= $clases ?></div>
        <div class="dato"><strong>Vencimiento:</strong> <?= $vencimiento ?></div>

        <div class="dato">
            <strong>Próximos turnos:</strong><br>
            <?php if ($turnos->num_rows > 0): ?>
                <ul>
                    <?php while($t = $turnos->fetch_assoc()): ?>
                        <li><?= $t['fecha'] . " " . $t['hora'] ?></li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                No tenés turnos próximos.
            <?php endif; ?>
        </div>

        <a class="btn" href="mi_qr.php?dni=<?= $dni ?>">Ver mi QR</a>
    </div>
</body>
</html>
