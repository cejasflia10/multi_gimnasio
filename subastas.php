<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$cliente_id || !$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

// Obtener subastas activas
$hoy = date('Y-m-d H:i:s');
$subastas = $conexion->query("
    SELECT * FROM subastas 
    WHERE gimnasio_id = $gimnasio_id AND fecha_cierre >= '$hoy'
    ORDER BY fecha_cierre ASC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subastas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">💰 Subastas Activas</h2>

    <?php while ($s = $subastas->fetch_assoc()):
        $subasta_id = $s['id'];

        // Obtener mejor oferta
        $mejor = $conexion->query("
            SELECT MAX(monto) AS mayor FROM subastas_ofertas 
            WHERE subasta_id = $subasta_id
        ")->fetch_assoc()['mayor'] ?? null;
    ?>
        <div class="card" style="margin-bottom: 15px;">
            <h3><?= htmlspecialchars($s['titulo']) ?></h3>
            <?php if ($s['imagen']): ?>
                <img src="<?= htmlspecialchars($s['imagen']) ?>" alt="Imagen" style="max-width:100%; max-height:200px; margin-bottom:10px;">
            <?php endif; ?>
            <p><?= nl2br(htmlspecialchars($s['descripcion'])) ?></p>
            <p><strong>Precio base:</strong> $<?= number_format($s['precio_base'], 2) ?></p>
            <p><strong>Fecha cierre:</strong> <?= $s['fecha_cierre'] ?></p>
            <p><strong>Mejor oferta:</strong> 
                <?= $mejor ? "$" . number_format($mejor, 2) : "Sin ofertas aún" ?>
            </p>

            <form method="POST" action="ofertar_subasta.php">
                <input type="hidden" name="subasta_id" value="<?= $subasta_id ?>">
                <input type="number" name="monto" step="0.01" min="<?= max($s['precio_base'], $mejor ?? 0) + 1 ?>" required placeholder="Tu oferta ($)">
                <button type="submit">📤 Ofertar</button>
            </form>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>
