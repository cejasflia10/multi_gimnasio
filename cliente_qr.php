<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$dni = '';
$cliente = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dni = trim($_POST['dni']);
    $consulta = $conexion->prepare("SELECT * FROM clientes WHERE dni = ?");
    $consulta->bind_param("s", $dni);
    $consulta->execute();
    $resultado = $consulta->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 30px;
        }
        input[type="text"] {
            padding: 12px;
            font-size: 18px;
            width: 80%;
            max-width: 300px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid gold;
            background-color: #111;
            color: gold;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: gold;
            color: black;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .cliente-info {
            margin-top: 30px;
            background-color: #111;
            padding: 20px;
            border-radius: 10px;
            display: inline-block;
        }
        .qr-img {
            margin-top: 20px;
        }
        .qr-img img {
            width: 200px;
            height: 200px;
        }
    </style>
</head>
<body>

    <h1>📲 Ingreso de Cliente</h1>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingresá tu DNI" required>
        <br>
        <button type="submit">Ingresar</button>
    </form>

    <?php if ($cliente): ?>
        <div class="cliente-info">
            <h2><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>
            <p><strong>Disciplina:</strong> <?= htmlspecialchars($cliente['disciplina']) ?></p>
            <p><strong>Clases disponibles:</strong> <?= $cliente['clases_disponibles'] ?? 0 ?></p>
            <p><strong>Vencimiento:</strong> <?= $cliente['fecha_vencimiento'] ?? 'No disponible' ?></p>

            <div class="qr-img">
                <?php
                $qr_path = "qr/" . $cliente['dni'] . ".png";
                if (file_exists($qr_path)) {
                    echo "<img src='$qr_path' alt='QR del cliente'>";
                } else {
                    echo "<p>⚠️ QR no generado. <a href='generar_qr_individual.php?id={$cliente['id']}' style='color:gold;'>Generar ahora</a></p>";
                }
                ?>
            </div>
        </div>
    <?php elseif ($_SERVER["REQUEST_METHOD"] === "POST"): ?>
        <p style="color: red;">⚠️ Cliente no encontrado. Verificá el DNI.</p>
    <?php endif; ?>

</body>
</html>
