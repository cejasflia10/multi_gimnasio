<?php
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $clases_disponibles = $_POST['clases_disponibles'];
    $plan_id = $_POST['plan_id'];

    // Plan adicional puede quedar vacío
    $adicional_id = !empty($_POST['adicional_id']) ? $_POST['adicional_id'] : null;

    // Otros pagos puede quedar vacío o 0
    $otros_pagos = isset($_POST['otros_pagos']) && is_numeric($_POST['otros_pagos']) ? $_POST['otros_pagos'] : 0;

    $metodo_pago = $_POST['metodo_pago'];
    $total = floatval($_POST['total_pagar']);

    // Si es cuenta corriente, registrar como saldo negativo
    if ($metodo_pago === 'Cuenta Corriente') {
        $total = -abs($total);
    }

    $gimnasio_id = $_SESSION['gimnasio_id'];

    $stmt = $conexion->prepare("INSERT INTO membresias 
        (cliente_id, fecha_inicio, fecha_vencimiento, clases_disponibles, plan_id, adicional_id, otros_pagos, metodo_pago, total, gimnasio_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("issiiiisdi", 
        $cliente_id, $fecha_inicio, $fecha_vencimiento, $clases_disponibles, $plan_id, 
        $adicional_id, $otros_pagos, $metodo_pago, $total, $gimnasio_id
    );

    if ($stmt->execute()) {
        echo "<script>alert('Membresía registrada correctamente.'); window.location.href='ver_membresias.php';</script>";
    } else {
        echo "Error al registrar membresía: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
}
?>
