<?php
session_start();
unset($_SESSION['cliente_id']);
header('Location: cliente_turnos.php');
exit;
