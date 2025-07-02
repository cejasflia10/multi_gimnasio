<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$cliente_id = $_SESSION['cliente_id'] ?? ($_GET['cliente_id'] ?? 0);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? ($_GET['gimnasio_id'] ?? 0);

if (!$cliente_id || !$gimnasio_id) {
    echo "<div style='color:red; font-size:20px; text-align:center;'>❌ Acceso denegado.</div>";
    exit;
}

// Si vino por GET y no hay sesión, la iniciamos
if (!isset($_SESSION['cliente_id']) && isset($_GET['cliente_id'])) {
    $_SESSION['cliente_id'] = $cliente_id;
    $_SESSION['gimnasio_id'] = $gimnasio_id;
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
