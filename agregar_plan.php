<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('configuraciones')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $duracion = intval($_POST['duracion_dias']);
    $limite = intval($_POST['limite_clientes']);
    $precio = floatval($_POST['precio']);
    $panel = isset($_POST['acceso_panel']) ? 1 : 0;
    $ventas = isset($_POST['acceso_ventas']) ? 1 : 0;
    $asistencias = isset($_POST['acceso_asistencias']) ? 1 : 0;

    $conexion->query("INSERT INTO plan_usuarios (nombre, duracion_dias, limite_clientes, precio, acceso_panel, acceso_ventas, acceso_asistencias) 
                      VALUES ('$nombre', $duracion, $limite, $precio, $panel, $ventas, $asistencias)");
    header("Location: configurar_planes.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="number"] {
            width: 100%; padding: 8px; margin-top: 5px; background: #222; color: gold; border: 1px solid #555;
        }
        input[type="submit"] {
            margin-top: 20px; padding: 10px 20px; background: gold; color: black; border: none; cursor: pointer;
        }
        input[type="submit"]:hover {
            background: #ffd700;
        }
    </style>
</head>
<body>
    <h1>➕ Agregar Plan para Gimnasios</h1>
    <form method="POST">
        <label>Nombre del Plan:</label>
        <input type="text" name="nombre" required>

        <label>Duración (en días):</label>
        <input type="number" name="duracion_dias" required>

        <label>Límite de clientes:</label>
        <input type="number" name="limite_clientes" required>

        <label>Precio mensual ($):</label>
        <input type="number" step="0.01" name="precio" required>

        <label><input type="checkbox" name="acceso_panel"> Acceso a Panel</label>
        <label><input type="checkbox" name="acceso_ventas"> Acceso a Ventas</label>
        <label><input type="checkbox" name="acceso_asistencias"> Acceso a Asistencias</label>

        <input type="submit" value="Guardar Plan">
    </form>
</body>
</html>
