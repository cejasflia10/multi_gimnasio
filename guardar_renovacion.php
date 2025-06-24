<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = intval($_POST['cliente_id']);
    $plan_id = intval($_POST['plan_id']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $precio = floatval($_POST['precio']);
    $clases_disponibles = intval($_POST['clases_disponibles']);
    $otros_pagos = floatval($_POST['otros_pagos']);
    $forma_pago = $_POST['forma_pago'];
    $total = floatval($_POST['total']);
    $gimnasio_id = intval($_POST['gimnasio_id']);
    $duracion_meses = intval($_POST['duracion_meses']);

    $stmt = $conexion->prepare("INSERT INTO membresias (
        cliente_id, plan_id, fecha_inicio, fecha_vencimiento, precio, clases_restantes,
        otros_pagos, forma_pago, total, gimnasio_id, duracion_meses
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
        die("Error en la preparación de la consulta: " . $conexion->error);
    }

    $stmt->bind_param("iissdidsdii",
        $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $precio,
        $clases_disponibles, $otros_pagos, $forma_pago, $total, $gimnasio_id, $duracion_meses
    );

    if ($stmt->execute()) {
        echo "<script>alert('Renovación registrada correctamente'); window.location.href='ver_membresias.php';</script>";
    } else {
        echo "<script>alert('Error al registrar: " . $stmt->error . "'); window.history.back();</script>";
    }

    $stmt->close();
} else {
    echo "<script>alert('Acceso no permitido'); window.location.href='index.php';</script>";
}
?>
