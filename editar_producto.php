<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$tipo = $_GET['tipo'] ?? '';
$id = intval($_GET['id'] ?? 0);

// Validar tipo de producto
$tipos_validos = ['proteccion', 'indumentaria', 'suplemento'];
if (!in_array($tipo, $tipos_validos)) {
    die("Tipo de producto inválido.");
}

$tabla = "productos_" . $tipo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $detalle = $_POST['detalle'];
    $precio_compra = floatval($_POST['precio_compra']);
    $precio_venta = floatval($_POST['precio_venta']);

    $stmt = $conexion->prepare("UPDATE $tabla SET nombre = ?, detalle = ?, precio_compra = ?, precio_venta = ? WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param("ssddii", $nombre, $detalle, $precio_compra, $precio_venta, $id, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<p style='color:lime'>✅ Producto actualizado correctamente.</p>";
    } else {
        echo "<p style='color:red'>❌ Error: " . $stmt->error . "</p>";
    }
    exit();
}

// Obtener datos del producto
$stmt = $conexion->prepare("SELECT * FROM $tabla WHERE id = ? AND gimnasio_id = ?");
$stmt->bind_param("ii", $id, $gimnasio_id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();

if (!$producto) {
    die("<p style='color:red'>❌ Producto no encontrado.</p>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 30px;
        }

        h2 {
            color: #ffc107;
        }

        form {
            max-width: 500px;
            margin: auto;
            background-color: #111;
            padding: 20px;
            border-radius: 10px;
        }

        label {
            display: block;
            margin-top: 15px;
            color: #ffc107;
        }

        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: none;
        }

        input[type="submit"] {
            margin-top: 20px;
            padding: 10px;
            width: 100%;
            background-color: #ffc107;
            color: #000;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>
    <h2>Editar Producto (<?= ucfirst($tipo) ?>)</h2>
    <form method="post">
        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?= $producto['nombre'] ?>" required>

        <label>Detalle:</label>
        <input type="text" name="detalle" value="<?= $producto['detalle'] ?>">

        <label>Precio Compra:</label>
        <input type="number" step="0.01" name="precio_compra" value="<?= $producto['precio_compra'] ?>" required>

        <label>Precio Venta:</label>
        <input type="number" step="0.01" name="precio_venta" value="<?= $producto['precio_venta'] ?>" required>

        <input type="submit" value="Guardar Cambios">
    </form>
</body>
</html>
