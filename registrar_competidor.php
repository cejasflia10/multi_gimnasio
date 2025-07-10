<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$profesor_id || !$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

// Obtener lista de clientes del gimnasio
$clientes = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE gimnasio_id = $gimnasio_id ORDER BY apellido, nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Competidor</title>
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial;
            padding: 20px;
        }
        form {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            max-width: 600px;
            margin: auto;
        }
        label, select, input, textarea {
            display: block;
            width: 100%;
            margin-bottom: 15px;
        }
        input, select, textarea {
            padding: 8px;
            border-radius: 5px;
            border: none;
        }
        button {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<h2 style="text-align:center;">🥊 Registrar Competidor</h2>

<form action="guardar_competidor.php" method="POST">
    <label>Seleccionar Cliente:</label>
    <select name="cliente_id" required>
        <option value="">-- Elegir cliente --</option>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>"><?= $c['apellido'] . ' ' . $c['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Disciplina:</label>
    <select name="disciplina" required>
        <option value="">-- Seleccionar --</option>
        <option value="Boxeo">Boxeo</option>
        <option value="Kickboxing">Kickboxing</option>
        <option value="K1">K1</option>
    </select>

    <label>Categoría:</label>
    <input type="text" name="categoria" placeholder="Ej: 60-65kg, hasta 18 años" required>

    <label>Observaciones:</label>
    <textarea name="observaciones" rows="3" placeholder="Ej: Tiene experiencia previa, campeón regional..."></textarea>

    <input type="hidden" name="profesor_id" value="<?= $profesor_id ?>">
    <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">

    <button type="submit">✅ Registrar Competidor</button>
</form>

</body>
</html>
