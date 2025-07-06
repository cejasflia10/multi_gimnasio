<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$cliente_id = $_GET['id'] ?? 0;
if (!$cliente_id) {
    echo "<div class='contenedor'><p class='error'>ID de cliente no proporcionado.</p></div>";
    exit;
}

$query = "SELECT * FROM fichas_habitos WHERE cliente_id = $cliente_id ORDER BY fecha DESC LIMIT 1";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    echo "<div class='contenedor'><p class='info'>No hay ficha registrada para este cliente.</p></div>";
    exit;
}

$ficha = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Hábitos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <div class="tarjeta">
        <h2 class="titulo-seccion">📄 Ficha de Hábitos - Cliente #<?= $cliente_id ?></h2>

        <?php foreach ($ficha as $campo => $valor): ?>
            <?php if (!in_array($campo, ['id', 'cliente_id'])): ?>
                <div class="grupo-campo">
                    <label class="etiqueta"><?= ucfirst(str_replace('_', ' ', $campo)) ?>:</label>
                    <div class="valor"><?= nl2br(htmlspecialchars($valor)) ?></div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="centrado">
            <a href="ver_clientes.php" class="boton volver">← Volver</a>
        </div>
    </div>
</div>

</body>
</html>
