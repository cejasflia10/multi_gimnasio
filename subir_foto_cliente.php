<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "Acceso denegado.";
    exit;
}

// Validar que venga el archivo
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
    $permitidos = ['image/jpeg', 'image/png', 'image/webp'];
    $tipo = $_FILES['foto']['type'];

    if (!in_array($tipo, $permitidos)) {
        echo "Formato no permitido.";
        exit;
    }

    // Convertir la imagen a base64
    $contenido = file_get_contents($_FILES['foto']['tmp_name']);
    $base64 = 'data:' . $tipo . ';base64,' . base64_encode($contenido);

    // Guardar en la base de datos
    $stmt = $conexion->prepare("UPDATE clientes SET foto_base64 = ? WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param('sii', $base64, $cliente_id, $gimnasio_id);
    if ($stmt->execute()) {
        header("Location: panel_cliente.php");
        exit;
    } else {
        echo "Error al guardar la imagen.";
    }
} else {
    echo "Error al subir la foto.";
}
?>
