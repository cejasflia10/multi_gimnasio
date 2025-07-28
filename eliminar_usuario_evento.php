<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 🔒 SOLO fightacademy y lucianoc pueden entrar
if (!isset($_SESSION['usuario']) || 
   ($_SESSION['usuario'] !== 'fightacademy' && $_SESSION['usuario'] !== 'lucianoc')) {
    echo "<p style='color:red; text-align:center; font-size:20px;'>🚫 No tienes permisos para acceder a esta página.</p>";
    exit;
}include 'conexion.php';

$id = $_GET['id'];
$conexion->query("DELETE FROM usuarios_evento WHERE id=$id");
header("Location: ver_usuarios_evento.php");
exit;
