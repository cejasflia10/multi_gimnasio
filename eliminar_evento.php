<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['evento_usuario_id'])) {
    header("Location: login_evento.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $conexion->query("DELETE FROM eventos_deportivos WHERE id = $id");
}

header("Location: panel_eventos.php");
exit;
?>
