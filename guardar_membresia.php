<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cliente_id = $_POST['cliente_id'];
    $plan_id = $_POST['plan_id'];
    $precio = $_POST['precio'];
    $clases = $_POST['clases_disponibles'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $pagos_adicionales = $_POST['pagos_adicionales'] ?? 0;
    $otros_pagos = $_POST['otros_pagos'] ?? 0;
    $forma_pago = $_POST['forma_pago'];
    $total = $_POST['total'];
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    $stmt = $conexion->prepare("INSERT INTO membresias (
        cliente_id, plan_id, precio, clases_disponibles,
        fecha_inicio, fecha_vencimiento, pagos_adicionales, otros_pagos,
        forma_pago, total, gimnasio_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("iidissddssi",
        $cliente_id, $plan_id, $precio, $clases,
        $fecha_inicio, $fecha_vencimiento, $pagos_adicionales, $otros_pagos,
        $forma_pago, $total, $gimnasio_id
    );

    if ($stmt->execute()) {
        echo "<script>alert('Membresía registrada correctamente'); window.location.href='ver_membresias.php';</script>";
    } else {
        echo "<script>alert('Error al registrar membresía: " . $stmt->error . "'); history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "<script>alert('Acceso inválido'); history.back();</script>";
}
?>
