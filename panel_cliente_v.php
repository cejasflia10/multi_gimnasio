<?php
// Mostrar errores (para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['cliente_id']) || empty($_SESSION['cliente_id'])) {
    echo "Acceso denegado.";
    exit;
}

include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'];
$cliente_nombre = $_SESSION['cliente_nombre'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .card {
            border: 1px solid gold;
            border-radius: 8px;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            background-color: #111;
        }
        .dato {
            margin: 10px 0;
        }
    </style>
</head>
<body>

<h2>👋 Bienvenido <?= $cliente['apellido'] . ' ' . $cliente['nombre'] ?></h2>

<div class="card">
    <div class="dato"><strong>DNI:</strong> <?= $cliente['dni'] ?></div>
    <div class="dato"><strong>Email:</strong> <?= $cliente['email'] ?></div>
    <div class="dato"><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></div>
    <div class="dato"><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></div>
</div>

</body>
</html>
