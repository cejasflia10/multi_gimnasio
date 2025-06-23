<?php
include 'conexion.php';

if (!isset($_GET['dni'])) {
    die("Acceso inválido.");
}

$dni = $_GET['dni'];

// Buscamos al cliente
$stmt = $conexion->prepare("SELECT * FROM clientes WHERE dni = ?");
$stmt->bind_param("s", $dni);
$stmt->execute();
$resultado = $stmt->get_result();

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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .panel {
            max-width: 600px;
            margin: auto;
            background-color: #222;
            padding: 30px;
            border-radius: 10px;
        }
        h2, p {
            margin-bottom: 15px;
        }
        .dorado {
            color: #ffd700;
        }
        .volver {
            margin-top: 20px;
            display: block;
            text-align: center;
            color: gold;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="panel">
        <h2>Bienvenido, <?php echo $cliente['nombre'] . " " . $cliente['apellido']; ?></h2>
        <p><span class="dorado">DNI:</span> <?php echo $cliente['dni']; ?></p>
        <p><span class="dorado">Disciplina:</span> <?php echo $cliente['disciplina']; ?></p>
        <p><span class="dorado">Fecha de nacimiento:</span> <?php echo $cliente['fecha_nacimiento']; ?></p>
        <p><span class="dorado">Email:</span> <?php echo $cliente['email']; ?></p>
        <p><span class="dorado">Teléfono:</span> <?php echo $cliente['telefono']; ?></p>

        <a class="volver" href="cliente_acceso.php">← Volver</a>
    </div>
</body>
</html>
