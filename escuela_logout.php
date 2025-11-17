<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/*
  Logout solo de la cuenta de ESCUELA.
  No tocamos la sesión de eventos, por si estás logueada también ahí.
*/
unset(
    $_SESSION['escuela_id'],
    $_SESSION['escuela_nombre'],
    $_SESSION['escuela_email']
);

// Podés elegir a dónde mandar después del logout:
// - a la pantalla de login de escuela
// - o al registro de escuela
header('Location: escuela_login.php');
// si preferís que vaya al registro, usá esto en lugar de la línea de arriba:
// header('Location: escuela_registro.php');

exit;
