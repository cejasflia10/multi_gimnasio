<?php
// Iniciar sesión correctamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
   

}

// Validar que exista sesión del cliente y gimnasio
$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "Acceso denegado.";
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
<!-- FOTO DEL CLIENTE -->
<div style="text-align:center; margin-top:30px;">
    <h3 style="color:gold;">📷 Foto del Cliente</h3>

    <?php
    if (!empty($cliente['foto']) && file_exists($cliente['foto'])) {
        echo '<img src="' . $cliente['foto'] . '" alt="Foto del Cliente" style="width:150px;height:150px;border-radius:50%;border:3px solid gold;object-fit:cover;">';
    } else {
        echo '<div style="width:150px;height:150px;border-radius:50%;border:3px solid gold;color:gold;display:flex;align-items:center;justify-content:center;margin:auto;">Sin foto</div>';
    }
    ?>

    <form action="subir_foto_cliente.php" method="post" enctype="multipart/form-data" style="margin-top:20px;">
        <input type="file" name="foto" accept="image/*" capture="user" required><br><br>
        <button type="submit" style="padding: 10px 20px; background: gold; color: black; border: none; border-radius: 5px;">Subir Foto</button>
    </form>
</div>


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
    if (isset($_POST['generar_qr'])) {
        require_once 'phpqrcode/qrlib.php';
        ob_start();
        QRcode::png($cliente['dni'], false, QR_ECLEVEL_H, 6);
        $imageString = base64_encode(ob_get_contents());
        ob_end_clean();
        echo "<div style='margin-top:20px;'><img src='data:image/png;base64,$imageString' alt='QR Cliente' style='width:200px; border: 3px solid gold; padding: 10px; background: white;'></div>";
    }
    ?>
</div>


</body>
</html>
