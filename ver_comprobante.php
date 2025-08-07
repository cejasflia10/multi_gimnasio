<?php
session_start();
include 'conexion.php';

if (!isset($_GET['id'])) {
    echo "ID no especificado.";
    exit;
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Validar que el gimnasio_id esté definido
if ($gimnasio_id == 0) {
    echo "Gimnasio no definido.";
    exit;
}

$sql = "SELECT archivo_comprobante FROM pagos_online WHERE id = $id AND gimnasio_id = $gimnasio_id";
$resultado = $conexion->query($sql);

if ($resultado && $resultado->num_rows > 0) {
    $fila = $resultado->fetch_assoc();
    $archivo = $fila['archivo_comprobante'];
    $ruta = __DIR__ . '/' . $archivo; // Ruta absoluta

    if (file_exists($ruta)) {
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

        // Cabeceras adecuadas
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'pdf':
                header('Content-Type: application/pdf');
                break;
            default:
                echo "Formato no soportado.";
                exit;
        }

        header('Content-Disposition: inline; filename="' . basename($archivo) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    } else {
        echo "No se encontró el comprobante.";
        exit;
    }
} else {
    echo "No se encontró el comprobante.";
    exit;
}
?>
