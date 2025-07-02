<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = $_POST['dni'] ?? '';

    if (empty($dni)) {
        echo "<script>alert('Ingresá tu DNI'); window.location.href='cliente_acceso.php';</script>";
        exit;
    }

    $sql = "SELECT id, gimnasio_id FROM clientes WHERE dni = '$dni'";
    $resultado = $conexion->query($sql);

    if ($resultado && $resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
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
