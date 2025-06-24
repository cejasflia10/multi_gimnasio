<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $plan_id = $_POST['plan_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $clases_disponibles = $_POST['clases_disponibles'];
    $precio = floatval($_POST['precio']);
    $otros_pagos = floatval($_POST['otros_pagos']);
    $forma_pago = $_POST['forma_pago'];
    $total = floatval($_POST['total']);

    // Insertar en la tabla de membresías
    $stmt = $conexion->prepare("INSERT INTO membresias 
        (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, precio, otros_pagos, forma_pago, total, gimnasio_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissiddsdi", 
        $cliente_id, $plan_id, 
        $fecha_inicio, $fecha_vencimiento, 
        $clases_disponibles, $precio, $otros_pagos, 
        $forma_pago, $total, $gimnasio_id
    );

    if ($stmt->execute()) {
        echo "<script>alert('Membresía registrada correctamente.'); window.location.href='ver_membresias.php';</script>";
    } else {
        echo "<script>alert('Error al registrar membresía.'); history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
}
?>
