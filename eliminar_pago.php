<?php
session_start();
include 'conexion.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("ID inválido");
}

// Eliminar asistencia del profesor
$conexion->query("DELETE FROM asistencias_profesor WHERE id = $id");

// Redirigir con mensaje
header("Location: pagar_horas_profesor.php?eliminado=1");
exit;
