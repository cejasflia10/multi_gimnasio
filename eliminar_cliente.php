<?php
session_start();
include 'conexion.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];

    // Validar que el cliente pertenezca al gimnasio del usuario logueado
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? null;

    if (!$gimnasio_id) {
        echo "Acceso denegado.";
        exit;
    }

    $stmt = $conexion->prepare("DELETE FROM clientes WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param("ii", $id, $gimnasio_id);

    if ($stmt->execute()) {
        header("Location: ver_clientes.php");
        exit();
    } else {
        echo "Error al eliminar cliente.";
    }

    $stmt->close();
} else {
    echo "ID de cliente no válido.";
}
?>
