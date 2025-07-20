<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$mensaje = "";

// Obtener datos del plan
$id = intval($_GET['id'] ?? 0);
$plan = $conexion->query("SELECT * FROM planes_acceso WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();

if (!$plan) {
    echo "<p style='color:red;'>❌ Plan no encontrado.</p>";
    exit;
}

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $duracion = intval($_POST['duracion'] ?? 1);
    $max_clientes = intval($_POST['max_clientes'] ?? 0);

    $clientes = isset($_POST['clientes']) ? 1 : 0;
    $asistencias = isset($_POST['asistencias']) ? 1 : 0;
    $competencias = isset($_POST['competencias']) ? 1 : 0;
    $profesores = isset($_POST['profesores']) ? 1 : 0;
    $ventas = isset($_POST['ventas']) ? 1 : 0;
    $panel = isset($_POST['panel']) ? 1 : 0;
    $configuraciones = isset($_POST['configuraciones']) ? 1 : 0;

    $stmt = $conexion->prepare("UPDATE planes_acceso SET 
        nombre = ?, precio = ?, duracion_meses = ?, max_clientes = ?,
        clientes = ?, asistencias = ?, competencias = ?, profesores = ?, ventas = ?, panel = ?, configuraciones = ?
        WHERE id = ? AND gimnasio_id = ?");

    $stmt->bind_param("sdiiiiiiiiiii", $nombre, $precio, $duracion, $max_clientes,
        $clientes, $asistencias, $competencias, $profesores, $ventas, $panel, $configuraciones,
        $id, $gimnasio_id);

    $stmt->execute();
    $mensaje = "<p style='color:lime;'>✅ Plan actualizado correctamente.</p>";

    // Recargar plan actualizado
    $plan = $conexion->query("SELECT * FROM planes_acceso WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Plan de Usuario</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        .formulario { background: #222; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto; }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="number"] {
            width: 100%; padding: 8px; background: #000; color: white; border: 1px solid gold;
        }
        input[type="checkbox"] { transform: scale(1.3); margin-right: 10px; }
        button {
            background: gold;
            color: black;
            padding: 10px 20px;
            font-weight: bold;
            margin-top: 20px;
            cursor: pointer;
            border: none;
            transition: background 0.3s ease;
        }
        button:hover {
            background: #ffcc00;
        }
    </style>
</head>
<body>

<div class="formulario">
    <h2>✏️ Editar Plan de Usuario</h2>
    <?= $mensaje ?>
    <form method="post">
        <label>Nombre del plan</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($plan['nombre']) ?>" required>

        <label>Precio mensual</label>
        <input type="number" step="0.01" name="precio" value="<?= $plan['precio'] ?>" required>

        <label>Duración del plan (meses)</label>
        <input type="number" name="duracion" min="1" value="<?= $plan['duracion_meses'] ?>" required>

        <label>Máximo de clientes permitidos</label>
        <input type="number" name="max_clientes" min="0" value="<?= $plan['max_clientes'] ?>" required>

        <label>Permisos visibles:</label>
        <label><input type="checkbox" name="clientes" <?= $plan['clientes'] ? 'checked' : '' ?>> Clientes</label>
        <label><input type="checkbox" name="asistencias" <?= $plan['asistencias'] ? 'checked' : '' ?>> Asistencias</label>
        <label><input type="checkbox" name="competencias" <?= $plan['competencias'] ? 'checked' : '' ?>> Competencias</label>
        <label><input type="checkbox" name="profesores" <?= $plan['profesores'] ? 'checked' : '' ?>> Profesores</label>
        <label><input type="checkbox" name="ventas" <?= $plan['ventas'] ? 'checked' : '' ?>> Ventas</label>
        <label><input type="checkbox" name="panel" <?= $plan['panel'] ? 'checked' : '' ?>> Panel Cliente</label>
        <label><input type="checkbox" name="configuraciones" <?= $plan['configuraciones'] ? 'checked' : '' ?>> Configuraciones</label>

        <button type="submit">💾 Guardar Cambios</button>
    </form>
</div>

</body>
</html>
