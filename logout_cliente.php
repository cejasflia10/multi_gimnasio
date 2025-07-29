<?php
session_start();
session_destroy();
header("Location: cliente_acceso.php");
exit();
?>
