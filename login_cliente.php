<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');

    if ($dni == '') {
        echo "<script>alert('Por favor, ingresá tu DNI'); window.location.href='cliente_acceso.php';</script>";
        exit;
    }

    $sql = "SELECT id, gimnasio_id FROM clientes WHERE dni = '$dni' LIMIT 1";
    $res = $conexion->query($sql);

    if ($res && $res->num_rows > 0) {
        $cliente = $res->fetch_assoc();
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['gimnasio_id'] = $cliente['gimnasio_id'];
        header("Location: panel_cliente.php");
        exit;
    } else {
        echo "<script>alert('❌ DNI no encontrado'); window.location.href='cliente_acceso.php';</script>";
        exit;
    }
}
?>
