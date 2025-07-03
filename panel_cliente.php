<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red; font-size:20px; text-align:center;'>❌ Acceso denegado.</div>";
    exit;
}

include 'conexion.php';
include 'menu_cliente.php';

// Validar que el cliente pertenezca al gimnasio
$cliente_id = $_SESSION['cliente_id'] ?? null;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;

if (!$cliente_id || !$gimnasio_id) {
    echo "Acceso denegado";
    exit;
}

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
if (!$cliente) {
    echo "Acceso denegado";
    exit;
}


if (!$cliente) {
    echo "<div style='color:red; text-align:center; font-size:20px;'>❌ Acceso denegado al gimnasio.</div>";
    exit;
}

$cliente_nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-top: 30px;
        }
        .datos {
            background: #111;
            padding: 20px;
            border-radius: 10px;
            max-width: 600px;
            margin: auto;
            border: 1px solid gold;
        }
    </style>
</head>
<body>

<h1>👋 Bienvenido <?= htmlspecialchars($cliente_nombre) ?></h1>
<?php if (!empty($cliente['foto_base64'])): ?>
    <img src="<?= $cliente['foto_base64'] ?>" alt="Foto del Cliente"
         style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid gold;">
<?php else: ?>
    <div style="width: 150px; height: 150px; border-radius: 50%; border: 3px solid gold; background: #333; color: gold; display: flex; align-items: center; justify-content: center;">
        Sin Foto
    </div>
<?php endif; ?>



<div class="datos">
    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
</div>

<div style="text-align: center; margin-top: 30px;">
    <h3 style="color: gold;">📲 Tu código QR personal</h3>
    <form method="post">
        <button type="submit" name="generar_qr" style="background-color: gold; color: black; font-weight: bold; padding: 10px 20px; border: none; border-radius: 5px;">Generar QR</button>
    </form>
<?php
include 'phpqrcode/qrlib.php'; // Asegurate de tener esta librería incluida

$dni_cliente = $_SESSION['dni'] ?? '';
if ($dni_cliente) {
    ob_start();
    QRcode::png('C' . $dni_cliente, false, QR_ECLEVEL_L, 6);
    $imageData = base64_encode(ob_get_clean());
    echo "<div style='text-align:center; margin-top:20px;'>
            <h3 style='color:gold;'>Mi QR de acceso</h3>
            <img src='data:image/png;base64,{$imageData}' alt='QR Cliente'>
          </div>";
}
?>

</div>


</body>
</html>
