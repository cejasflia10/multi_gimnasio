<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$c1 = intval($_GET['cliente_id'] ?? 0);
$c2 = intval($_GET['cliente_receptor_id'] ?? 0);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$mensajes = $conexion->query("
    SELECT * FROM mensajes_chat 
    WHERE gimnasio_id = $gimnasio_id AND (
        (cliente_id = $c1 AND cliente_receptor_id = $c2) OR
        (cliente_id = $c2 AND cliente_receptor_id = $c1)
    )
    ORDER BY fecha ASC
");

while ($m = $mensajes->fetch_assoc()) {
    $clase = ($m['cliente_id'] == $c1) ? 'cliente' : 'profesor'; // cliente = emisor, "profesor" usado como receptor visual
    echo "<div class='mensaje $clase'>";
    echo htmlspecialchars($m['mensaje']);
    echo "<br><small style='color:gray; font-size:12px;'>{$m['fecha']}</small>";
    echo "</div>";
}

// marcar como leídos si venís como receptor
$conexion->query("
    UPDATE mensajes_chat 
    SET leido = 1 
    WHERE gimnasio_id = $gimnasio_id AND cliente_receptor_id = $c1 AND cliente_id = $c2 AND emisor = 'cliente'
");
