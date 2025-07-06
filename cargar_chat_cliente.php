<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$tipo = $_GET['tipo'] ?? '';
$destino_id = intval($_GET['id'] ?? 0);

if (!$cliente_id || !$destino_id || !$tipo || !$gimnasio_id) exit;

if ($tipo === 'profesor') {
    $res = $conexion->query("
        SELECT * FROM mensajes_chat 
        WHERE gimnasio_id = $gimnasio_id 
        AND cliente_id = $cliente_id 
        AND profesor_id = $destino_id
        ORDER BY fecha ASC
    ");
} elseif ($tipo === 'cliente') {
    $res = $conexion->query("
        SELECT * FROM mensajes_chat 
        WHERE gimnasio_id = $gimnasio_id AND (
            (cliente_id = $cliente_id AND cliente_receptor_id = $destino_id)
            OR 
            (cliente_id = $destino_id AND cliente_receptor_id = $cliente_id)
        )
        ORDER BY fecha ASC
    ");
} else {
    exit;
}

while ($m = $res->fetch_assoc()) {
    $clase = ($m['cliente_id'] == $cliente_id) ? 'cliente' : 'profesor';
    echo "<div class='mensaje $clase'>";
    echo htmlspecialchars($m['mensaje']);
    echo "<br><small style='color:gray;'>{$m['fecha']}</small>";
    echo "</div>";
}

// Marcar como leídos
if ($tipo === 'profesor') {
    $conexion->query("
        UPDATE mensajes_chat 
        SET leido = 1 
        WHERE gimnasio_id = $gimnasio_id 
        AND cliente_id = $cliente_id 
        AND profesor_id = $destino_id 
        AND emisor = 'profesor'
    ");
} elseif ($tipo === 'cliente') {
    $conexion->query("
        UPDATE mensajes_chat 
        SET leido = 1 
        WHERE gimnasio_id = $gimnasio_id 
        AND cliente_id = $destino_id 
        AND cliente_receptor_id = $cliente_id 
        AND emisor = 'cliente'
    ");
}
