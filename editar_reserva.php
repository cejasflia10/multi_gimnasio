<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Simplemente redirige al panel de reservas con el mismo día seleccionado
header("Location: reservar_turno_cliente.php?dia=" . ($_POST['turno_id'] ?? 1));
exit;
?>
