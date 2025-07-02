<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? null;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;

if (!$cliente_id || !$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $foto = $_FILES['foto'];

    if ($foto['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
        $nombre_archivo = 'cliente_' . $cliente_id . '_' . time() . '.' . $ext;
        $ruta_destino = 'fotos_clientes/' . $nombre_archivo;

        if (!file_exists('fotos_clientes')) {
            mkdir('fotos_clientes', 0755, true);
        }

        if (move_uploaded_file($foto['tmp_name'], $ruta_destino)) {
            $conexion->query("UPDATE clientes SET foto = '$ruta_destino' WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id");
            header("Location: panel_cliente.php");
            exit;
        } else {
            echo "Error al mover la foto.";
        }
    } else {
        echo "Error al subir la foto.";
    }
} else {
    echo "No se recibió ninguna imagen.";
}
?>
