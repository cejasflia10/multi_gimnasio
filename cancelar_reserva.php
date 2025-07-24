<?php
if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

function mostrar_error($mensaje) {
    echo "<!DOCTYPE html><html lang='es'><head>
        <meta charset='UTF-8'>
        <title>Error</title>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <link rel='stylesheet' href='estilo_unificado.css'>
    </head><body>
    <div class='contenedor'>
        <h2 style='color:red;'>$mensaje</h2>
        <a href='ver_turnos_cliente.php' class='btn-principal'>Volver</a>
    </div></body></html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mostrar_error("❌ Solicitud inválida.");
}

if (!isset($_POST['turno_id']) || !is_numeric($_POST['turno_id'])) {
    mostrar_error("❌ Turno no especificado.");
}

$turno_id = intval($_POST['turno_id']);

// Eliminar reserva solo si coincide cliente y gimnasio
$conexion->query("
    DELETE FROM reservas_clientes 
    WHERE cliente_id = $cliente_id 
      AND turno_id = $turno_id 
      AND gimnasio_id = $gimnasio_id
");

header("Location: ver_turnos_cliente.php?ok_cancel=1");
exit;
?>
