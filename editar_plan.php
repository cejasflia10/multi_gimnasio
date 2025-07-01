<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['id'])) {
    die("ID de plan no especificado.");
}

$id = intval($_GET['id']);
$query = "SELECT * FROM planes WHERE id = $id";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Plan no encontrado.");
}

$plan = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        form {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background-color: #000;
            color: gold;
            border: 1px solid gold;
        }
        input[type="submit"] {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }
        a {
            display: inline-block;
            margin-top: 15px;
            color: #ccc;
            text-align: center;
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

<h1>Editar Plan</h1>
<form action="guardar_plan.php" method="post">
    <input type="hidden" name="id" value="<?= $plan['id'] ?>">
    
    <label for="nombre">Nombre:</label>
    <input type="text" name="nombre" value="<?= htmlspecialchars($plan['nombre'] ?? '') ?>" required>
    
    <label for="precio">Precio:</label>
    <input type="text" name="precio" value="<?= htmlspecialchars($plan['precio'] ?? '') ?>" required>

    <label for="dias_disponibles">Días disponibles:</label>
    <input type="number" name="dias_disponibles" value="<?= htmlspecialchars($plan['dias_disponibles'] ?? '') ?>" required>

    <label for="duracion_meses">Duración (meses):</label>
    <input type="number" name="duracion_meses" value="<?= htmlspecialchars($plan['duracion_meses'] ?? '') ?>" required>

    <input type="submit" value="Guardar cambios">
</form>

<div style="text-align:center;">
    <a href="planes.php">Volver</a>
</div>

</body>
</html>
