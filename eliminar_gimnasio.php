<?php include 'verificar_sesion.php'; ?>
<?php
include 'conexion.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ID no válido";
    exit();
}

// Eliminar registro
$conexion->query("DELETE FROM gimnasios WHERE id = $id");

header("Location: gimnasios.php");
exit();
?>
