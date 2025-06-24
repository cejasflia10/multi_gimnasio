<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cliente_id = $_POST['cliente_id'];
    $plan_id = $_POST['plan_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $clases_restantes = $_POST['clases_disponibles'];
    $metodo_pago = $_POST['forma_pago'];
    $otros_pagos = $_POST['otros_pagos'] ?? 0;
    $total = $_POST['total'];
    $adicional_id = $_POST['adicional_id'] ?? null;
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    $stmt = $conexion->prepare("INSERT INTO membresias (
        cliente_id, plan_id, fecha_inicio, fecha_vencimiento,
        clases_restantes, metodo_pago, otros_pagos, total,
        adicional_id, gimnasio_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("iississdii",
        $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento,
        $clases_restantes, $metodo_pago, $otros_pagos, $total,
        $adicional_id, $gimnasio_id
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
