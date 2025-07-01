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
$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();

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

<div class="datos">
    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
</div>


<!-- Mostrar foto del cliente y formulario -->
<div style="text-align:center; margin-top: 30px;">
<?php
$foto_path = "fotos_clientes/" . $_SESSION['cliente_id'] . ".jpg";
if (file_exists($foto_path)) {
    echo "<img src='$foto_path' alt='Mi Foto' style='width:120px;height:120px;border-radius:50%;border:2px solid gold;margin:10px 0;'>";
} else {
    echo "<img src='fotos_clientes/default.jpg' alt='Sin Foto' style='width:120px;height:120px;border-radius:50%;border:2px solid gray;margin:10px 0;'>";
}
?>
<form action="subir_foto_cliente.php" method="POST" enctype="multipart/form-data" style="margin-top:10px;">
    <label style="color:gold;">Subir o cambiar mi foto:</label><br>
    <input type="file" name="foto" accept="image/*" capture="environment" style="margin:5px 0;"><br>
    <button type="submit" style="background-color:gold;color:black;padding:5px 10px;border:none;border-radius:5px;">Subir Foto</button>
</form>

<!-- Mostrar QR del cliente -->
<?php
$dni = $cliente['dni'];
$qr_url = "https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=C$dni";
?>
<div style="margin-top: 20px;">
    <h3 style="color:gold;">🎫 Tu código QR personal</h3>
    <img src="<?= $qr_url ?>" alt="QR Cliente" style="width:180px;height:180px; border:4px solid gold; border-radius:10px; background:#fff; padding:10px;">
    <p style="color:gold;">Escaneá este código al ingresar al gimnasio</p>
</div>
</div>

</body>
</html>
