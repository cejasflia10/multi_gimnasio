<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$dni_cliente = $cliente['dni'] ?? '';

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red; font-size:20px; text-align:center;'>❌ Acceso denegado.</div>";
    exit;
}

// Verificar si el cliente pertenece al gimnasio
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
        .foto {
            text-align: center;
            margin: 20px auto;
        }
        .foto img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid gold;
        }
        .form-foto {
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<h1>👋 Bienvenido <?= htmlspecialchars($cliente_nombre) ?></h1>

<div class="foto">
    <?php
    $foto = $cliente['foto'];
    $ruta_foto = "fotos_clientes/" . $foto;

    if (!empty($foto) && file_exists($ruta_foto)) {
        echo "<img src='$ruta_foto' alt='Foto del cliente'>";
    } else {
        echo "<img src='fotos_clientes/default.png' alt='Sin foto' style='opacity:0.7;'>";
    }
    ?>
</div>

<div class="datos">
    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
</div>
<?php
// ...
echo "<h3 style='color: gold;'>📲 Tu código QR personal</h3>";

include 'phpqrcode/qrlib.php';
$dni_cliente = $cliente['dni'] ?? ''; // <-- cambio clave aquí

if (!empty($dni_cliente)) {
    ob_start();
    QRcode::png('C' . $dni_cliente, false, QR_ECLEVEL_L, 6);
    $imageData = base64_encode(ob_get_clean());
    echo "<img src='data:image/png;base64,{$imageData}' alt='QR Cliente' style='margin-top:10px;'>";
} else {
    echo "<p style='color:red;'>No se pudo generar el QR (DNI no disponible).</p>";
}
?>

</div>

<div class="form-foto">
    <form method="POST" enctype="multipart/form-data">
        <label for="nueva_foto" style="color:#FFD700;">📸 Subí tu foto (o tomá con la cámara)</label><br><br>
        <input type="file" name="nueva_foto" accept="image/*" capture="user" required><br><br>
        <button type="submit" style="padding:5px 15px; background:#FFD700; border:none; border-radius:5px;">Cargar foto</button>
    </form>
</div>

</body>
</html>

<?php
// Subida de foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['nueva_foto'])) {
    if ($_FILES['nueva_foto']['error'] === UPLOAD_ERR_OK) {
        $foto_tmp = $_FILES['nueva_foto']['tmp_name'];
        $nombre_archivo = 'cliente_' . $cliente_id . '_' . time() . '.jpg';
        $ruta_destino = 'fotos_clientes/' . $nombre_archivo;

        if (move_uploaded_file($foto_tmp, $ruta_destino)) {
            $conexion->query("UPDATE clientes SET foto = '$nombre_archivo' WHERE id = $cliente_id");
            echo "<script>location.href='panel_cliente.php';</script>";
            exit;
        } else {
            echo "<p style='color:red; text-align:center;'>Error al guardar la imagen.</p>";
        }
    }
}
?>
