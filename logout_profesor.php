<?php
session_start();
session_unset();
session_destroy();
header("Location: login_profesor.php");
exit;
?>
