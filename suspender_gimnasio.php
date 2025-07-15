<?php
session_start();
include 'conexion.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $conexion->query("UPDATE gimnasios SET activo = 0 WHERE id = $id LIMIT 1");
    header("Location: panel_gimnasios.php");
    exit;
} else {
    echo "❌ ID inválido.";
}
