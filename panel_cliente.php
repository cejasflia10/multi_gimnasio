
<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) {
    header("Location: login_cliente.php");
    exit;
}

include 'menu_cliente.php';

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id")->fetch_assoc();
$datos = $conexion->query("SELECT * FROM datos_personales_cliente WHERE cliente_id = $cliente_id")->fetch_assoc();

$foto = (!empty($cliente['foto']) && file_exists($cliente['foto'])) ? $cliente['foto'] : 'fotos/default.jpg';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🏠 Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: #222;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px gold;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .foto {
            text-align: center;
            margin-bottom: 20px;
        }
        .foto img {
            width: 180px;
            border: 3px solid gold;
            border-radius: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        td {
            padding: 10px;
            border: 1px solid gold;
        }
        td.titulo {
            font-weight: bold;
            background-color: #111;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>🏋️ Bienvenido/a <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

    <div class="foto">
        <img src="<?= $foto ?>" alt="Foto de perfil">
    </div>

    <table>
        <tr><td class="titulo">DNI</td><td><?= $cliente['dni'] ?></td></tr>
        <tr><td class="titulo">Fecha de nacimiento</td><td><?= $cliente['fecha_nacimiento'] ?></td></tr>
        <tr><td class="titulo">Edad</td><td><?= $cliente['edad'] ?> años</td></tr>
        <tr><td class="titulo">Teléfono</td><td><?= $cliente['telefono'] ?></td></tr>
        <tr><td class="titulo">Email</td><td><?= $cliente['email'] ?></td></tr>
        <tr><td class="titulo">Domicilio</td><td><?= $cliente['domicilio'] ?></td></tr>
        <tr><td class="titulo">Disciplina</td><td><?= $cliente['disciplina'] ?></td></tr>
        <tr><td class="titulo">Fecha de vencimiento</td><td><?= $cliente['fecha_vencimiento'] ?></td></tr>
    </table>
</div>
</body>
</html>
