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
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h2><?= $editando ? 'Editar' : 'Agregar nuevo' ?> plan</h2>

    <form method="post" action="guardar_plan.php">
        <?php if ($editando): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <label>Nombre del plan:
            <input type="text" name="nombre" value="<?= htmlspecialchars($plan['nombre']) ?>" required>
        </label>

        <label>Precio:
            <input type="number" name="precio" step="0.01" value="<?= $plan['precio'] ?>" required>
        </label>

        <label>Clases disponibles:
            <input type="number" name="clases_disponibles" value="<?= $plan['clases_disponibles'] ?>" required>
        </label>

        <label>Días disponibles:
            <input type="number" name="dias_disponibles" value="<?= $plan['dias_disponibles'] ?>">
        </label>

        <label>Duración (en meses):
            <input type="number" name="duracion" value="<?= $plan['duracion'] ?>" min="1" required>
        </label>

        <button type="submit"><?= $editando ? 'Actualizar' : 'Guardar' ?> Plan</button>
    </form>

    <br>
    <a href="ver_planes.php">⬅ Volver al listado de planes</a>
</div>

</body>
</html>
