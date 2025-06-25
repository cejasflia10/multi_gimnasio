<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cliente_id = $_POST['cliente_id'];
    $membresia_id = $_POST['membresia_id'];
    $fecha = $_POST['fecha'];
    $monto = $_POST['monto'];
    $forma_pago = $_POST['forma_pago'];
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    $stmt = $conexion->prepare("INSERT INTO pagos 
        (cliente_id, membresia_id, fecha, monto, forma_pago, gimnasio_id, fecha_pago) 
        VALUES (?, ?, ?, ?, ?, ?, CURDATE())");

    $stmt->bind_param("iidsis", $cliente_id, $membresia_id, $fecha, $monto, $forma_pago, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Pago registrado correctamente'); window.location.href='ver_pagos.php';</script>";
    } else {
        echo "<script>alert('Error al registrar pago: " . $stmt->error . "'); history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
}
?>
