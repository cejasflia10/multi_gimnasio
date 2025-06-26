<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function verificar_acceso($roles_permitidos = []) {
    $rol = $_SESSION['rol'] ?? '';
    if (!in_array($rol, $roles_permitidos)) {
        echo "<h2 style='color:red; text-align:center;'>Acceso denegado</h2>";
        exit;
    }
}
