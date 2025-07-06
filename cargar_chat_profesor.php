<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = intval($_GET['cliente_id'] ?? 0);

if (!$profesor_id || !$cliente_id || !$gimnasio_id) exit;

$res = $conexion->query("
    SELECT * FROM mensajes_chat 
    WHERE gimnasio_id = $gimnasio_id 
    AND cliente_id = $cliente_id 
    AND profesor_id = $profesor_id
    ORDER BY fecha ASC
");

while ($m = $res->fetch_assoc()) {
    $clase = ($m['emisor'] == 'profesor') ? 'profesor' : 'cliente';
    echo "<div class='mensaje $clase'>";
    echo htmlspecialchars($m['mensaje']);
    echo "<br><small style='color:gray;'>{$m['fecha']}</small>";
    echo "</div>";
}

// marcar como leídos los mensajes del cliente
$conexion->query("
    UPDATE mensajes_chat 
    SET leido = 1 
    WHERE gimnasio_id = $gimnasio_id 
    AND cliente_id = $cliente_id 
    AND profesor_id = $profesor_id 
    AND emisor = 'cliente'
");
