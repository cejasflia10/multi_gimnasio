<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) die('Acceso denegado.');

$id_gimnasio = $_SESSION['id_gimnasio'];
$tipo = $_GET['tipo'] ?? '';
$id = intval($_GET['id'] ?? 0);
$tabla = "productos_" . $tipo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $detalle = $_POST['detalle'];
    $precio_compra = floatval($_POST['precio_compra']);
    $precio_venta = floatval($_POST['precio_venta']);

    $stmt = $conexion->prepare("UPDATE $tabla SET nombre = ?, detalle = ?, precio_compra = ?, precio_venta = ? WHERE id = ? AND id_gimnasio = ?");
    $stmt->bind_param("ssddii", $nombre, $detalle, $precio_compra, $precio_venta, $id, $id_gimnasio);

    if ($stmt->execute()) {
        echo "✅ Producto actualizado.";
    } else {
        echo "❌ Error: " . $stmt->error;
    }
    exit();
}

$stmt = $conexion->prepare("SELECT * FROM $tabla WHERE id = ? AND id_gimnasio = ?");
$stmt->bind_param("ii", $id, $id_gimnasio);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
if (!$producto) die("Producto no encontrado.");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
</head>
<body style="background:#111; color:#fff; font-family:Arial; padding:30px;">
    <h2>Editar Producto (<?php echo ucfirst($tipo); ?>)</h2>
    <form method="post">
        <label>Nombre:</label><br>
        <input type="text" name="nombre" value="<?php echo $producto['nombre']; ?>" required><br>
        <label>Detalle:</label><br>
        <input type="text" name="detalle" value="<?php echo $producto['detalle']; ?>"><br>
        <label>Precio Compra:</label><br>
        <input type="number" step="0.01" name="precio_compra" value="<?php echo $producto['precio_compra']; ?>" required><br>
        <label>Precio Venta:</label><br>
        <input type="number" step="0.01" name="precio_venta" value="<?php echo $producto['precio_venta']; ?>" required><br>
        <input type="submit" value="Guardar Cambios">
    </form>
</body>
</html>
