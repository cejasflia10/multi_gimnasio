<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['cliente_id']) || !isset($_SESSION['gimnasio_id'])) {
    echo "<div style='color:red; font-size:20px; text-align:center;'>❌ Acceso denegado.</div>";
    exit;
}

$cliente_id = $_SESSION['cliente_id'];
$gimnasio_id = $_SESSION['gimnasio_id'];

include 'conexion.php';
// El resto de tu lógica sigue aquí...

include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'];
$cliente_nombre = $_SESSION['cliente_nombre'] ?? 'Cliente';

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id")->fetch_assoc();
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
        pre {
            background-color: #111;
            color: lime;
            padding: 10px;
            margin: 20px auto;
            max-width: 600px;
            overflow: auto;
        }
    </style>
</head>
<body>

<h1>👋 Bienvenido <?= htmlspecialchars($cliente_nombre) ?></h1>
<?php if (!empty($cliente['foto_base64'])): ?>
    <img src="<?= $cliente['foto_base64'] ?>" alt="Foto del Cliente" style="width:150px;height:150px;border-radius:50%;object-fit:cover;border:3px solid gold;">
<?php else: ?>
    <div style="width:150px;height:150px;border-radius:50%;border:3px solid gold;background:#333;color:gold;display:flex;align-items:center;justify-content:center;margin:auto;">
        Sin Foto
    </div>
<?php endif; ?>

<!-- Formulario para subir nueva foto -->
<form action="subir_foto_cliente.php" method="post" enctype="multipart/form-data" style="margin-top:20px;">
    <input type="file" name="foto" accept="image/*" capture="user" required><br><br>
    <button type="submit" style="padding: 10px 20px; background: gold; color: black; border: none; border-radius: 5px;">Subir Foto</button>
</form>

<div class="datos">
    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
</div>
<!-- Generar QR -->
<div style="margin-top: 40px;">
    <h3>📲 Tu código QR personal</h3>
    <?php
    include_once 'phpqrcode/qrlib.php';
    ob_start();
    QRcode::png($cliente['dni'], false, QR_ECLEVEL_H, 5);
    $imageString = base64_encode(ob_get_clean());
    ?>
    <img src="data:image/png;base64,<?= $imageString ?>" alt="QR Cliente" style="width:200px; border: 3px solid gold; padding: 10px; background: white;">
</div>

<pre>🧪 SESIÓN ACTUAL:
<?php print_r($_SESSION); ?>
</pre>

</body>
</html>
