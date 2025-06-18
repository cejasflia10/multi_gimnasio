<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID de cliente no proporcionado.");
}

$id = intval($_GET['id']);

$stmt = $conexion->prepare("SELECT id, dni, nombre, apellido FROM clientes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows == 0) {
    die("Cliente no encontrado.");
}

$cliente = $resultado->fetch_assoc();

include 'phpqrcode/qrlib.php';
$contenido = $cliente['id'] . " - " . $cliente['dni'] . " - " . $cliente['nombre'] . " " . $cliente['apellido'];

$dir = "qr_clientes/";
if (!file_exists($dir)) {
    mkdir($dir, 0777, true);
}

$nombre_archivo = $dir . "cliente_" . $cliente['id'] . ".png";
QRcode::png($contenido, $nombre_archivo);

// Mostrar imagen
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>QR del Cliente</title>
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 40px;
        }
        img {
            margin-top: 20px;
            border: 4px solid gold;
        }
        a {
            color: gold;
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>QR del Cliente</h1>
    <p><?php echo $contenido; ?></p>
    <img src="<?php echo $nombre_archivo; ?>" alt="QR Cliente">
    <br>
    <a href="clientes.php">Volver a Clientes</a>
</body>
</html>
