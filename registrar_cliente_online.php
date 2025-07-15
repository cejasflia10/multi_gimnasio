<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = intval($_GET['gimnasio'] ?? 0);

// Obtener disciplinas
$disciplinas = [];
$res = $conexion->query("SELECT id, nombre FROM disciplinas WHERE gimnasio_id = $gimnasio_id");
while ($fila = $res->fetch_assoc()) {
    $disciplinas[] = $fila;
}

// Obtener datos del gimnasio y configuración (sin validación de existencia)
$gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$config = $conexion->query("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = $gimnasio_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Online - <?= htmlspecialchars($gimnasio['nombre'] ?? 'Gimnasio') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <?php if (!empty($config['mostrar_logo_pdf']) && file_exists("logos/logo_$gimnasio_id.png")): ?>
        <img src="logos/logo_<?= $gimnasio_id ?>.png" alt="Logo del Gimnasio" class="logo">
    <?php endif; ?>

    <h2>Registro Online</h2>

    <?php if (!empty($config['mensaje_bienvenida'])): ?>
        <div class="bienvenida"><?= nl2br(htmlspecialchars($config['mensaje_bienvenida'])) ?></div>
    <?php endif; ?>

    <form action="guardar_cliente_online.php" method="post">
        <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">

        <label>Apellido:</label>
        <input type="text" name="apellido" required>

        <label>Nombre:</label>
        <input type="text" name="nombre" required>

        <label>DNI:</label>
        <input type="number" name="dni" required>

        <label>Fecha de nacimiento:</label>
        <input type="date" name="fecha_nacimiento" required>

        <label>Domicilio:</label>
        <input type="text" name="domicilio" required>

        <label>Teléfono:</label>
        <input type="text" name="telefono" required>

        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Disciplina:</label>
        <select name="disciplina" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($disciplinas as $disciplina): ?>
                <option value="<?= htmlspecialchars($disciplina['nombre']) ?>">
                    <?= htmlspecialchars($disciplina['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="submit" value="Registrar Cliente">
    </form>
</div>

</body>
</html>
