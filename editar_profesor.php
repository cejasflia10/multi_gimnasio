<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['id'])) {
    die("ID del profesor no especificado.");
}

$id = intval($_GET['id']);
$resultado = $conexion->query("SELECT * FROM profesores WHERE id = $id");

if ($resultado->num_rows === 0) {
    die("Profesor no encontrado.");
}

$profesor = $resultado->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $dni = $_POST['dni'];
    $domicilio = $_POST['domicilio'];
    $telefono = $_POST['telefono'];

    $stmt = $conexion->prepare("UPDATE profesores SET apellido=?, nombre=?, dni=?, domicilio=?, telefono=? WHERE id=?");
    $stmt->bind_param("sssssi", $apellido, $nombre, $dni, $domicilio, $telefono, $id);
    $stmt->execute();

    echo "<script>alert('Profesor actualizado correctamente'); window.location.href='ver_profesores.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
        }

        h2 {
            text-align: center;
            color: gold;
            margin-bottom: 30px;
        }

        form {
            background-color: #222;
            padding: 20px;
            border-radius: 12px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.2);
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input[type="text"], input[type="tel"], input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid gold;
            border-radius: 6px;
            background-color: #000;
            color: gold;
            font-size: 16px;
        }

        button {
            margin-top: 25px;
            width: 100%;
            padding: 12px;
            background-color: gold;
            color: black;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #e6b800;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            form {
                padding: 15px;
            }

            h2 {
                font-size: 20px;
            }
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
    <h2>Editar Datos del Profesor</h2>
    <form method="POST">
        <label for="apellido">Apellido:</label>
        <input type="text" name="apellido" value="<?= htmlspecialchars($profesor['apellido']) ?>" required>

        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($profesor['nombre']) ?>" required>

        <label for="dni">DNI:</label>
        <input type="number" name="dni" value="<?= htmlspecialchars($profesor['dni']) ?>" required>

        <label for="domicilio">Domicilio:</label>
        <input type="text" name="domicilio" value="<?= htmlspecialchars($profesor['domicilio']) ?>">

        <label for="telefono">Teléfono:</label>
        <input type="tel" name="telefono" value="<?= htmlspecialchars($profesor['telefono']) ?>">

        <button type="submit">Guardar Cambios</button>
    </form>
</body>
</html>
