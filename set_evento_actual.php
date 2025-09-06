<?php
// set_evento_actual.php
if (session_status() === PHP_SESSION_NONE) session_start();

$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
$next      = isset($_GET['next']) ? $_GET['next'] : 'ver_eventos.php';

if ($evento_id > 0) {
  $_SESSION['evento_id_actual'] = $evento_id;
}

header('Location: ' . $next . (strpos($next, '?') === false ? '?': '&') . 'evento_id=' . $evento_id);
exit;
