<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

$subastas = $conexion->query("
    SELECT * FROM subastas 
    WHERE gimnasio_id = $gimnasio_id 
    ORDER BY fecha_cierre DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Subastas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">📋 Subastas del Gimnasio</h2>

    <?php while ($s = $subastas->fetch_assoc()):
        $subasta_id = $s['id'];
        $estado = (strtotime($s['fecha_cierre']) >= time()) ? 'Activa' : 'Cerrada';

        // Buscar mejor oferta
        $mejor = $conexion->query("
            SELECT monto, cliente_id FROM subastas_ofertas 
            WHERE subasta_id = $subasta_id 
            ORDER BY monto DESC LIMIT 1
        ")->fetch_assoc();

        // Asignar ganador automáticamente si está cerrada y no tiene aún
        if ($estado == 'Cerrada' && !$s['ganador_id'] && $mejor) {
            $ganador_id = $mejor['cliente_id'];
            $conexion->query("UPDATE subastas SET ganador_id = $ganador_id WHERE id = $subasta_id");
            $s['ganador_id'] = $ganador_id;
        }

        // Obtener nombre del ganador
        $ganador = '—';
        if ($s['ganador_id']) {
            $g = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = {$s['ganador_id']}")->fetch_assoc();
            if ($g) {
                $ganador = $g['apellido'] . ', ' . $g['nombre'];
            }
        }

        // Obtener cantidad de ofertas
        $cantidad = $conexion->query("SELECT COUNT(*) AS total FROM subastas_ofertas WHERE subasta_id = $subasta_id")->fetch_assoc()['total'];
    ?>
        <div class="card" style="margin-bottom:15px;">
            <h3><?= htmlspecialchars($s['titulo']) ?></h3>
            <p><strong>Fecha cierre:</strong> <?= $s['fecha_cierre'] ?> — 
                <span style="color:<?= $estado == 'Activa' ? 'lime' : 'red' ?>"><?= $estado ?></span>
            </p>
            <p><strong>Precio base:</strong> $<?= number_format($s['precio_base'], 2) ?></p>
            <p><strong>Ofertas:</strong> <?= $cantidad ?></p>

            <?php if ($estado == 'Cerrada' && $s['ganador_id']): ?>
                <p style="color:gold;"><strong>Ganador:</strong> <?= $ganador ?> 🏆</p>
                <p><strong>Mejor oferta:</strong> $<?= number_format($mejor['monto'], 2) ?></p>
            <?php elseif ($estado == 'Cerrada'): ?>
                <p style="color:orange;"><strong>Sin ofertas, sin ganador</strong></p>
            <?php else: ?>
                <p><strong>Mejor oferta:</strong> <?= $mejor ? '$' . number_format($mejor['monto'], 2) : '—' ?></p>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>
