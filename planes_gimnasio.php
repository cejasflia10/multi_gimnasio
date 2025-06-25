<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

// Verificamos si hay sesión y el gimnasio_id está presente
if (!isset($_SESSION['gimnasio_id'])) {
    die("Error: No se encuentra el ID del gimnasio.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $clases = intval($_POST['clases']);

    if (!empty($nombre) && $precio > 0 && $clases > 0) {
        $stmt = $conexion->prepare("INSERT INTO planes_gimnasio (nombre, precio, clases, gimnasio_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdii", $nombre, $precio, $clases, $gimnasio_id);

        if ($stmt->execute()) {
            echo "Plan agregado correctamente.";
        } else {
            echo "Error al agregar plan: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Todos los campos son obligatorios.";
    }
}
?>
