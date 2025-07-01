<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "conexion.php";
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = $_GET['id'] ?? null;
$editando = false;

$plan = [
    'nombre' => '',
    'precio' => '',
    'clases_disponibles' => '',
    'dias_disponibles' => '',
    'duracion' => 1,
];

if ($id) {
    $editando = true;
    $res = $conexion->query("SELECT * FROM planes WHERE id = $id AND gimnasio_id = $gimnasio_id");
    if ($res && $res->num_rows > 0) {
        $plan = $res->fetch_assoc();
    } else {
        die("Plan no encontrado.");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $editando ? 'Editar' : 'Agregar' ?> Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; margin: 0; }
        h1 { text-align: center; font-size: 24px; margin-bottom: 20px; }
        form { max-width: 500px; margin: auto; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input, select, button {
            width: 100%; padding: 10px; margin-top: 5px;
            background-color: #111; color: gold;
            border: 1px solid gold; border-radius: 5px;
        }
        button {
            background-color: gold; color: black; font-weight: bold; margin-top: 20px;
        }
        a { display: block; text-align: center; color: gold; margin-top: 20px; text-decoration: none; }
        @media (max-width: 600px) {
            body { padding: 10px; }
            h1 { font-size: 20px; }
        }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>
<h1><?= $editando ? 'Editar' : 'Agregar nuevo' ?> plan</h1>
<form method="post" action="guardar_plan.php">
    <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= $id ?>">
    <?php endif; ?>

    <label>Nombre del plan:
        <input type="text" name="nombre" value="<?= $plan['nombre'] ?>" required>
    </label>

    <label>Precio:
        <input type="number" name="precio" step="0.01" value="<?= $plan['precio'] ?>" required>
    </label>

    <label>Clases disponibles:
        <input type="number" name="clases_disponibles" value="<?= $plan['clases_disponibles'] ?>" required>
    </label>

    <label>Días disponibles:
        <input type="number" name="dias_disponibles" value="<?= $plan['dias_disponibles'] ?>" required>
    </label>

    <label>Duración (en meses):
        <input type="number" name="duracion" value="<?= $plan['duracion'] ?>" required>
    </label>

    <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">

    <button type="submit"><?= $editando ? 'Actualizar' : 'Guardar' ?></button>
</form>

<a href="planes.php">&larr; Volver a Planes</a>
</body>
</html>
