<?php
// Mostrar errores (para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['cliente_id']) || empty($_SESSION['cliente_id'])) {
    echo "Acceso denegado.";
    exit;
}

include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'];
$cliente_nombre = $_SESSION['cliente_nombre'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Cliente</title>
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
            margin-bottom: 20px;
        }
        .card {
            border: 1px solid gold;
            border-radius: 8px;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            background-color: #111;
        }
        .dato {
            margin: 10px 0;
        }
    </style>
</head>
<body>
<?php
$foto_path = "fotos_clientes/" . $_SESSION['cliente_id'] . ".jpg";
if (file_exists($foto_path)) {
    echo "<img src='$foto_path' alt='Mi Foto' style='width:120px;height:120px;border-radius:50%;border:2px solid gold;margin:10px 0;'>";
} else {
    echo "<img src='fotos_clientes/default.jpg' alt='Sin Foto' style='width:120px;height:120px;border-radius:50%;border:2px solid gray;margin:10px 0;'>";
}
?>

<h2>👋 Bienvenido <?= $cliente['apellido'] . ' ' . $cliente['nombre'] ?></h2>

<div class="card">
    <div class="dato"><strong>DNI:</strong> <?= $cliente['dni'] ?></div>
    <div class="dato"><strong>Email:</strong> <?= $cliente['email'] ?></div>
    <div class="dato"><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></div>
    <div class="dato"><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></div>
</div>
<?php
// Mostrar QR personal generado en tiempo real (con DNI)
$dni = $cliente['dni'];
include 'phpqrcode/qrlib.php'; // Asegúrate de tener esta librería incluida

// Generar QR temporal (solo si estás en un entorno que lo permita)
ob_start();
QRcode::png("C$dni", null, QR_ECLEVEL_L, 4);
$imageData = ob_get_contents();
ob_end_clean();
$base64 = base64_encode($imageData);
?>

<div style="text-align:center; margin-top: 20px;">
    <h3>📲 Tu código QR personal</h3>
    <img src="data:image/png;base64,<?= $base64 ?>" alt="QR Cliente" style="width:180px;height:180px;">
</div>

<!-- Subir foto del cliente -->
<form action="subir_foto_cliente.php" method="POST" enctype="multipart/form-data" style="text-align:center; margin-top:20px;">
    <label style="font-weight:bold;">📸 Subí tu foto (desde cámara o galería)</label><br><br>
    <input type="file" name="foto" accept="image/*" capture="environment" required><br><br>
    <input type="submit" value="Cargar Foto" style="padding:8px 15px; background:gold; border:none; font-weight:bold; border-radius:8px;">
</form>

</body>
</html>
