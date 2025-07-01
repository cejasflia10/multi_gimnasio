<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mensaje = "";

// Agregar nuevo producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    $nombre = $_POST['nombre'];
    $detalle = $_POST['detalle'];
    $compra = $_POST['compra'];
    $venta = $_POST['venta'];
    $categoria = $_POST['categoria'];

    $stmt = $conexion->prepare("INSERT INTO productos (nombre, detalle, compra, venta, categoria, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddsi", $nombre, $detalle, $compra, $venta, $categoria, $gimnasio_id);
    if ($stmt->execute()) {
        $mensaje = "✅ Producto registrado exitosamente.";
    } else {
        $mensaje = "❌ Error al registrar: " . $stmt->error;
    }
}

// Obtener categorías de productos
$categorias = $conexion->query("SELECT * FROM categorias ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background-color: #000;
            border-radius: 10px;
        }

        h1 {
            text-align: center;
            color: #ffc107;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: none;
        }

        .btn {
            margin-top: 20px;
            width: 100%;
            padding: 10px;
            background: #ffc107;
            color: #000;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        .mensaje {
            margin-top: 20px;
            text-align: center;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .container {
                margin: 20px;
            }
        }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>

<div class="container">
    <h1>Agregar Producto</h1>
    <form method="POST">
        <label>Nombre del Producto:</label>
        <input type="text" name="nombre" required>

        <label>Detalle:</label>
        <input type="text" name="detalle" required>

        <label>Precio de Compra:</label>
        <input type="number" step="0.01" name="compra" required>

        <label>Precio de Venta:</label>
        <input type="number" step="0.01" name="venta" required>

        <label>Categoría:</label>
        <select name="categoria" required>
            <option value="">-- Seleccionar Categoría --</option>
            <?php while ($categoria = $categorias->fetch_assoc()): ?>
                <option value="<?= $categoria['id'] ?>"><?= $categoria['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <button type="submit" class="btn">Registrar Producto</button>
    </form>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>
</div>
</body>
</html>
