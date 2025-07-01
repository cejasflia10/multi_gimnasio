<?php
// Mostrar errores si hay fallo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

// Verificar sesión válida antes de mostrar nada
$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) {
    echo "Acceso denegado.";
    exit;
}

include 'menu_cliente.php'; // Solo si la sesión es válida

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id")->fetch_assoc();
$foto = $cliente['foto'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['nueva_foto'])) {
    $nombre_archivo = "fotos_clientes/" . uniqid() . "_" . basename($_FILES['nueva_foto']['name']);
    if (move_uploaded_file($_FILES['nueva_foto']['tmp_name'], $nombre_archivo)) {
        $conexion->query("UPDATE clientes SET foto = '$nombre_archivo' WHERE id = $cliente_id");
        $foto = $nombre_archivo;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .foto {
            text-align: center;
            margin-bottom: 20px;
        }
        .foto img {
            max-width: 150px;
            border-radius: 10px;
            border: 2px solid gold;
        }
        form {
            text-align: center;
        }
    </style>
</head>
<body>

<h2>Bienvenido <?= htmlspecialchars($cliente['apellido'] . ', ' . $cliente['nombre']) ?></h2>

<div class="foto">
    <?php if (!empty($foto)): ?>
        <img src="<?= $foto ?>" alt="Foto del cliente">
    <?php else: ?>
        <p>Sin foto cargada</p>
    <?php endif; ?>
</div>

<form method="POST" enctype="multipart/form-data">
    <label>Cargar nueva foto:</label><br><br>
    <input type="file" name="nueva_foto" required><br><br>
    <input type="submit" value="Subir Foto">
</form>

</body>
</html>
