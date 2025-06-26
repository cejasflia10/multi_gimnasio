<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('configuraciones')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

if (!isset($_GET['id'])) {
    echo "<h2 style='color:red;'>ID de gimnasio no especificado</h2>";
    exit;
}

$id = intval($_GET['id']);
$gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = $id")->fetch_assoc();

if (!$gimnasio) {
    echo "<h2 style='color:red;'>Gimnasio no encontrado</h2>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $panel = isset($_POST['acceso_panel']) ? 1 : 0;
    $ventas = isset($_POST['acceso_ventas']) ? 1 : 0;
    $asistencias = isset($_POST['acceso_asistencias']) ? 1 : 0;
    $usuarios = isset($_POST['acceso_usuarios']) ? 1 : 0;

    $conexion->query("UPDATE gimnasios SET 
        acceso_panel = $panel, 
        acceso_ventas = $ventas, 
        acceso_asistencias = $asistencias, 
        acceso_usuarios = $usuarios 
        WHERE id = $id");

    header("Location: configurar_accesos.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Accesos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 { text-align: center; }
        form {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
        }
        label {
            display: block;
            margin: 15px 0;
        }
        input[type="submit"] {
            padding: 10px 20px;
            background: gold;
            color: black;
            border: none;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background: #ffd700;
        }
    </style>
</head>
<body>

<h1>✏️ Editar Accesos para: <?= htmlspecialchars($gimnasio['nombre']) ?></h1>

<form method="POST">
    <label><input type="checkbox" name="acceso_panel" <?= $gimnasio['acceso_panel'] ? 'checked' : '' ?>> Acceso a Panel</label>
    <label><input type="checkbox" name="acceso_ventas" <?= $gimnasio['acceso_ventas'] ? 'checked' : '' ?>> Acceso a Ventas</label>
    <label><input type="checkbox" name="acceso_asistencias" <?= $gimnasio['acceso_asistencias'] ? 'checked' : '' ?>> Acceso a Asistencias</label>
    <label><input type="checkbox" name="acceso_usuarios" <?= $gimnasio['acceso_usuarios'] ? 'checked' : '' ?>> Acceso a Usuarios</label>

    <input type="submit" value="Guardar Cambios">
</form>

</body>
</html>
