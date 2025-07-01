<?php
include 'conexion.php';

$cliente_id = $_GET['cliente_id'] ?? 0;
$cliente_id = intval($cliente_id);

if ($cliente_id <= 0) {
    echo "❌ Acceso denegado.";
    exit;
}

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id")->fetch_assoc();
if (!$cliente) {
    echo "❌ Cliente no encontrado.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="background:black; color:gold; font-family:Arial; padding:20px;">
    <h2>Bienvenido <?= htmlspecialchars($cliente['apellido'] . ', ' . $cliente['nombre']) ?></h2>

    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>

    <!-- Opcional: botón para volver -->
    <br><a href="login_cliente.php" style="color:lightblue;">Cerrar sesión</a>
</body>
</html>
