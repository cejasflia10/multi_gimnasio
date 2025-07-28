<?php
session_start();
session_destroy();
header("Location: acceso_cliente.php");
exit();
?>
