<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include("conexion.php");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Mostrar errores

include 'menu_horizontal.php';

if (!isset($_GET['id'])) {
    die("ID de usuario no especificado.");
}

$id = intval($_GET['id']);
$query = "SELECT * FROM usuarios WHERE id = $id";
$resultado = $conexion->query($query) or die("Error en consulta: " . $conexion->error);

if ($resultado->num_rows === 0) {
    die("Usuario no encontrado.");
}

$usuario = $resultado->fetch_assoc();

// Obtener gimnasios disponibles
$gimnasios_resultado = $conexion->query("SELECT id, nombre FROM gimnasios");
?>
