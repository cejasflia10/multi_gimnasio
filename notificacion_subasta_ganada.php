<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$cliente_id || !$gimnasio_id) exit;

$subastas = $conexion->query("
    SELECT s.titulo, so.monto 
    FROM subastas s
    JOIN subastas_ofertas so ON s.id = so.subasta_id
    WHERE s.ganador_id = $cliente_id 
      AND s.gimnasio_id = $gimnasio_id
      AND so.cliente_id = $cliente_id
    ORDER BY s.fecha_cierre DESC
");

if ($subastas->num_rows > 0):
    while ($s = $subastas->fetch_assoc()):
?>
    <div class="alerta-mensaje" style="margin-top:10px;">
        🏆 Has ganado la subasta: <strong><?= htmlspecialchars($s['titulo']) ?></strong><br>
        💰 Oferta ganadora: <strong>$<?= number_format($s['monto'], 2) ?></strong>
    </div>
<?php
    endwhile;
endif;
