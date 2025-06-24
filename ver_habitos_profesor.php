<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$cliente_id = $_GET['id'] ?? 0;
if (!$cliente_id) {
    die("ID de cliente no proporcionado.");
}

$query = "SELECT * FROM fichas_habitos WHERE cliente_id = $cliente_id ORDER BY fecha DESC LIMIT 1";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    echo "<p style='color:gold; text-align:center;'>No hay ficha registrada para este cliente.</p>";
    exit;
}

$ficha = $resultado->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha del Cliente</title>
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
        }
        .ficha {
            max-width: 700px;
            margin: auto;
            background-color: #111;
            padding: 20px;
            border-radius: 8px;
        }
        .campo {
            margin-bottom: 15px;
        }
        .campo label {
            font-weight: bold;
        }
        .campo span {
            display: block;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <h2>Ficha de Hábitos - Cliente ID: <?php echo $cliente_id; ?></h2>
    <div class="ficha">
        <?php foreach ($ficha as $campo => $valor): ?>
            <div class="campo">
                <label><?php echo ucfirst(str_replace('_', ' ', $campo)); ?>:</label>
                <span><?php echo htmlspecialchars($valor); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
