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
 <?php
$foto = $cliente['foto'];
$ruta_foto = "fotos_clientes/" . $foto;

// Validamos que exista la foto físicamente y que no esté vacía
if (!empty($foto) && file_exists($ruta_foto)) {
    echo "<img src='$ruta_foto' alt='Foto del cliente' style='width:150px; height:150px; border-radius:50%; object-fit:cover; border: 2px solid gold;'>";
} else {
    echo "<img src='fotos_clientes/default.png' alt='Sin foto' style='width:150px; height:150px; border-radius:50%; object-fit:cover; opacity:0.7;'>";
}
?>

<pre>🧪 SESIÓN ACTUAL:
<?php print_r($_SESSION); ?>
</pre>

</body>
</html>
