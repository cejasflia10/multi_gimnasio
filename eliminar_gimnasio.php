<?php
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $conexion->query("DELETE FROM gimnasios WHERE id = $id");
}
header("Location: gimnasios.php");
exit;
?>
