<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?? null;
    $plan_id = $_POST['plan_id'] ?? null;
    $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
    $adicional_id = !empty($_POST['adicional_id']) ? $_POST['adicional_id'] : null;
    $otros_pagos = isset($_POST['otros_pagos']) && is_numeric($_POST['otros_pagos']) ? floatval($_POST['otros_pagos']) : 0;
    $metodo_pago = $_POST['metodo_pago'] ?? null;

    if (!$cliente_id || !$plan_id || !$metodo_pago) {
        echo "<script>alert('Faltan campos obligatorios.'); history.back();</script>";
        exit;
    }

    // Obtener datos del plan
    $stmt_plan = $conexion->prepare("SELECT precio, clases FROM planes WHERE id = ?");
    $stmt_plan->bind_param("i", $plan_id);
    $stmt_plan->execute();
    $res_plan = $stmt_plan->get_result();
    $plan = $res_plan->fetch_assoc();
    $precio_plan = $plan['precio'] ?? 0;
    $clases_disponibles = $plan['clases'] ?? 0;
    $stmt_plan->close();

    // Obtener precio adicional si se seleccionó
    $precio_adicional = 0;
    if ($adicional_id) {
        $stmt_add = $conexion->prepare("SELECT precio FROM planes_adicionales WHERE id = ?");
        $stmt_add->bind_param("i", $adicional_id);
        $stmt_add->execute();
        $res_add = $stmt_add->get_result();
        $add = $res_add->fetch_assoc();
        $precio_adicional = $add['precio'] ?? 0;
        $stmt_add->close();
    }

    $total = $precio_plan + $precio_adicional + $otros_pagos;
    if (strtolower($metodo_pago) === 'cuenta corriente') {
        $total = -abs($total);
    }

    $fecha_vencimiento = date('Y-m-d', strtotime($fecha_inicio . ' +30 days'));
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
    if (!$gimnasio_id) {
        echo "<script>alert('Sesión no válida.'); window.location.href='index.php';</script>";
        exit;
    }

    // Guardar clases disponibles y restantes
    $clases_restantes = $clases_disponibles;

    $stmt = $conexion->prepare("INSERT INTO membresias 
        (cliente_id, fecha_inicio, fecha_vencimiento, plan_id, adicional_id, otros_pagos, metodo_pago, total, gimnasio_id, clases_disponibles, clases_restantes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("issiiisdiii",
        $cliente_id, $fecha_inicio, $fecha_vencimiento, $plan_id, $adicional_id,
        $otros_pagos, $metodo_pago, $total, $gimnasio_id, $clases_disponibles, $clases_restantes
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
