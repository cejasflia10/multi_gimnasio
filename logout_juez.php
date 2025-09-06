<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* Limpiar solo sesión del juez */
unset($_SESSION['juez_id'], $_SESSION['juez_nombre'], $_SESSION['juez_apellido']);
unset($_SESSION['__JUEZ_MODE__']); // quitar bypass al salir

header('Location: login_juez.php');
exit;
