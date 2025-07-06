<?php
include 'conexion.php';

$cliente_id = intval($_GET['cliente_id'] ?? 0);
$profesor_id = intval($_GET['profesor_id'] ?? 0);

if ($cliente_id == 0 || $profesor_id == 0) {
    exit;
}

// Traer todos los mensajes entre cliente y profesor
$mensajes = $conexion->query("
    SELECT * FROM mensajes_chat 
    WHERE cliente_id = $cliente_id AND profesor_id = $profesor_id 
    ORDER BY fecha ASC
");

// Mostrar mensajes con clases para formato
while ($m = $mensajes->fetch_assoc()) {
    $clase = ($m['emisor'] === 'cliente') ? 'cliente' : 'profesor';
    echo "<div class='mensaje $clase'>";
    echo htmlspecialchars($m['mensaje']);
    echo "<br><small style='color:gray; font-size:12px;'>{$m['fecha']}</small>";
    echo "</div>";
}

// Marcar como leídos si el receptor está viendo el chat
if (isset($_SESSION['cliente_id']) && $_SESSION['cliente_id'] == $cliente_id) {
    // El cliente está leyendo => marcar mensajes del profesor como leídos
    $conexion->query("
        UPDATE mensajes_chat 
        SET leido = 1 
        WHERE cliente_id = $cliente_id AND profesor_id = $profesor_id AND emisor = 'profesor'
    ");
} elseif (isset($_SESSION['profesor_id']) && $_SESSION['profesor_id'] == $profesor_id) {
    // El profesor está leyendo => marcar mensajes del cliente como leídos
    $conexion->query("
        UPDATE mensajes_chat 
        SET leido = 1 
        WHERE cliente_id = $cliente_id AND profesor_id = $profesor_id AND emisor = 'cliente'
    ");
}
