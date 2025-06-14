<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) die('Acceso denegado.');

$id_gimnasio = $_SESSION['id_gimnasio'];
$tipo = $_GET['tipo'] ?? '';
$id = intval($_GET['id'] ?? 0);
$tabla = "productos_" . $tipo;

$stmt = $conexion->prepare("DELETE FROM $tabla WHERE id = ? AND id_gimnasio = ?");
$stmt->bind_param("ii", $id, $id_gimnasio);

if ($stmt->execute()) {
    echo "✅ Producto eliminado.";
} else {
    echo "❌ Error: " . $stmt->error;
}
?>
<a href="ver_productos.php?tipo=<?php echo $tipo; ?>" style="color:#ffc107;">← Volver</a>
