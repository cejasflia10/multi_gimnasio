<?php
session_start();
include 'conexion.php';
$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");
include 'menu_cliente.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📄 Mis Archivos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h1>📄 Mis Archivos</h1>
    <p style="text-align: center;">Contenido disponible próximamente o en construcción.</p>
</div>
</body>
</html>
