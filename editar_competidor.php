<?php
session_start();
include 'conexion.php';

$id = intval($_GET['id'] ?? 0);
$mensaje = "";

// Obtener datos actuales
$res = $conexion->query("SELECT * FROM competidores WHERE id = $id");
if (!$res || $res->num_rows == 0) {
    echo "<p style='color:red;'>Competidor no encontrado.</p>";
    exit;
}
$comp = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $disciplina = $_POST['disciplina'] ?? '';
    $division = $_POST['division'] ?? '';

    $conexion->query("UPDATE competidores SET disciplina = '$disciplina', division = '$division' WHERE id = $id");
    $mensaje = "<p style='color:lime;'>✅ Datos actualizados correctamente.</p>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Competidor</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        .form-box { max-width: 600px; margin: auto; background: #222; padding: 20px; border-radius: 10px; }
        label { display: block; margin-top: 10px; }
        select, button {
            width: 100%; padding: 10px; margin-top: 5px;
            background: #000; color: white; border: 1px solid gold;
        }
        button { background: gold; color: black; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
<div class="form-box">
    <h2>✏️ Editar Competidor</h2>
    <?= $mensaje ?>
    <form method="POST">
        <label>Disciplina</label>
        <select name="disciplina" required>
            <option value="Boxeo" <?= $comp['disciplina'] == 'Boxeo' ? 'selected' : '' ?>>Boxeo</option>
            <option value="Kickboxing" <?= $comp['disciplina'] == 'Kickboxing' ? 'selected' : '' ?>>Kickboxing</option>
            <option value="K1" <?= $comp['disciplina'] == 'K1' ? 'selected' : '' ?>>K1</option>
        </select>

        <label>División</label>
        <select name="division" required>
            <option value="Exhibición" <?= $comp['division'] == 'Exhibición' ? 'selected' : '' ?>>Exhibición</option>
            <option value="Amateur" <?= $comp['division'] == 'Amateur' ? 'selected' : '' ?>>Amateur</option>
            <option value="ProAm" <?= $comp['division'] == 'ProAm' ? 'selected' : '' ?>>ProAm</option>
            <option value="Profesional" <?= $comp['division'] == 'Profesional' ? 'selected' : '' ?>>Profesional</option>
        </select>

        <button type="submit">💾 Guardar cambios</button>
    </form>
</div>
</body>
</html>
