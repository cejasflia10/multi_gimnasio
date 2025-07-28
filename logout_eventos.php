<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔹 Cierra todas las variables de sesión
$_SESSION = [];

// 🔹 Destruye la sesión completamente
session_destroy();

// 🔹 Redirige al login de eventos
header("Location: login_evento.php");
exit;
