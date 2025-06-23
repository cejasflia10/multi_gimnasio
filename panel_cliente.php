<?php
session_start();
include 'conexion.php';

if (!isset($_GET['dni'])) {
    die("DNI no proporcionado.");
}

$dni = $_GET['dni'];

// Buscar cliente
$query = "SELECT * FROM clientes WHERE dni = '$dni'";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Cliente no encontrado.");
}

$cliente = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px gold;
        }

        h2 {
            text-align: center;
            color: gold;
        }

        p {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .qr-container {
            text-align: center;
            margin-top: 20px;
        }

        .qr-container img {
            max-width: 200px;
            border: 3px solid gold;
            padding: 5px;
        }

        .btn-descargar {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
            margin-top: 10px;
        }

        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 15px;
            }
            .btn-descargar {
                padding: 8px 16px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Bienvenido <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>

    <div class="qr-container">
        <h3>Tu Código QR</h3>
        <?php
        $qr_path = "qr/cliente_" . $cliente['id'] . ".png";
        if (file_exists($qr_path)):
        ?>
            <img src="<?= $qr_path ?>" alt="Código QR"><br>
            <a href="<?= $qr_path ?>" download="QR_<?= $cliente['dni'] ?>.png" class="btn-descargar">Descargar QR</a>
        <?php else: ?>
            <p style="color: #aaa;">Tu QR aún no ha sido generado.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
