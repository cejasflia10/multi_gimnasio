<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = intval($_GET['cliente_id'] ?? 0);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id > 0 && $gimnasio_id > 0) {
    $conexion->query("DELETE FROM cuentas_corrientes WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id AND monto < 0");
    header("Location: ver_cuentas_corrientes.php");
    exit;
} else {
    echo "<div style='color:red; text-align:center; padding:20px;'>❌ Error al eliminar la deuda.</div>";
}
