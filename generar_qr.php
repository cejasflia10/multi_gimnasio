<?php
include 'conexion.php'; // Asegurate de tener la conexión funcionando

$cliente = null;

if (isset($_GET['dni']) && $_GET['dni'] != "") {
    $dni = $_GET['dni'];
    $stmt = $conexion->prepare("SELECT * FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
    } else {
        $mensaje = "Cliente no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar QR de Cliente</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        input, button {
            padding: 10px;
            margin: 5px;
        }

        .resultado {
            margin-top: 20px;
        }

        a {
            color: #ffc107;
            font-weight: bold;
        }
    </style>
</head>
<body>

<h2>Buscar Cliente por DNI para generar QR</h2>

<form method="GET" action="">
    <input type="text" name="dni" placeholder="Ingrese DNI" required>
    <button type="submit">Buscar</button>
</form>

<?php if (isset($mensaje)) echo "<p style='color: red;'>$mensaje</p>"; ?>

<?php if ($cliente): ?>
    <div class="resultado">
        <h3>Cliente encontrado:</h3>
        <p><strong>Nombre:</strong> <?= $cliente['nombre'] ?> <?= $cliente['apellido'] ?></p>
        <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>

        <a href="generar_qr.php?dni=<?= $cliente['dni'] ?>" target="_blank">➡ Generar código QR</a>
    </div>
<?php endif; ?>

</body>
</html>
