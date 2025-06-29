<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

$dni = $_GET['codigo'] ?? '';
$dni = trim($dni);

if ($dni === '') {
    echo "<div style='text-align:center; color:red; margin-top:50px; font-size:20px;'>❌ Código QR vacío<br><a href='javascript:history.back()' style='color:violet'>← Volver</a></div>";
    exit;
}

// Redirige al registro de profesor
header("Location: registrar_asistencia_profesor.php?dni=" . urlencode($dni));
exit;
?>
