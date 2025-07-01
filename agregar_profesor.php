<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

// Función para generar QR en base64
function generarQR($dni) {
    include_once 'phpqrcode/qrlib.php';
    $tempDir = 'qrs/';
    if (!file_exists($tempDir)) mkdir($tempDir);
    $filename = $tempDir . 'qr_' . $dni . '.png';
    QRcode::png($dni, $filename, QR_ECLEVEL_H, 4);
    return $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $domicilio = $_POST['domicilio'];
    $telefono = $_POST['telefono'];
    $dni = $_POST['dni'];
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    $qr_path = generarQR($dni);

    $stmt = $conexion->prepare("INSERT INTO profesores (apellido, nombre, domicilio, telefono, dni, qr_codigo, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $apellido, $nombre, $domicilio, $telefono, $dni, $qr_path, $gimnasio_id);
    $stmt->execute();

    header("Location: ver_profesores.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        form { max-width: 400px; margin: auto; }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="number"] {
            width: 100%; padding: 8px; background: #222; color: gold; border: 1px solid #444;
        }
        .botones {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        input[type="submit"], a {
            background: gold; color: black; padding: 10px 20px; text-decoration: none;
            font-weight: bold; border: none; border-radius: 5px;
        }
        input[type="submit"]:hover, a:hover {
            background: #ffd700;
        }
    </style>
</head>
<script>
// Reactivar pantalla completa con el primer clic
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function entrarPantallaCompleta() {
        if (!document.fullscreenElement && body.requestFullscreen) {
            body.requestFullscreen().catch(err => {
                console.warn("No se pudo activar pantalla completa:", err);
            });
        }
    }

    // Activar pantalla completa al hacer clic
    body.addEventListener('click', entrarPantallaCompleta, { once: true });
});

// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones como F12, Ctrl+Shift+I
document.addEventListener('keydown', function (e) {
    if (
        e.key === "F12" ||
        (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J")) ||
        (e.ctrlKey && e.key === "U")
    ) {
        e.preventDefault();
    }
});
</script>

<body>

<h1>➕ Agregar Profesor</h1>

<form method="POST">
    <label>Apellido:</label>
    <input type="text" name="apellido" required>

    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <label>Domicilio:</label>
    <input type="text" name="domicilio">

    <label>Teléfono:</label>
    <input type="text" name="telefono">

    <label>DNI:</label>
    <input type="text" name="dni" required>

    <div class="botones">
        <input type="submit" value="Guardar">
        <a href="index.php">Volver al menú</a>
    </div>
</form>

</body>
</html>
