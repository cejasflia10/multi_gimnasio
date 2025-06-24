<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$cliente_id = $_POST['cliente_id'];
$plan_id = $_POST['plan_id'];
$clases_disponibles = $_POST['clases_disponibles'];
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_vencimiento = $_POST['fecha_vencimiento'];
$otros_pagos = $_POST['otros_pagos'] ?? 0;
$metodo_pago = $_POST['metodo_pago'];
$total = $_POST['total'] ?? 0;
$adicionales = $_POST['adicionales'] ?? [];

$conexion->begin_transaction();

try {
    // Insertar la membresía principal
    $stmt = $conexion->prepare("INSERT INTO membresias (cliente_id, plan_id, clases_disponibles, fecha_inicio, fecha_vencimiento, otros_pagos, metodo_pago, total, id_gimnasio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiisssdsi", $cliente_id, $plan_id, $clases_disponibles, $fecha_inicio, $fecha_vencimiento, $otros_pagos, $metodo_pago, $total, $gimnasio_id);
    $stmt->execute();
    $membresia_id = $stmt->insert_id;

    // Insertar adicionales si los hay
    foreach ($adicionales as $adicional_id) {
        $stmt_ad = $conexion->prepare("INSERT INTO membresias_adicionales (membresia_id, adicional_id) VALUES (?, ?)");
        $stmt_ad->bind_param("ii", $membresia_id, $adicional_id);
        $stmt_ad->execute();
    }

    $conexion->commit();
    header("Location: ver_membresias.php");
    exit;
} catch (Exception $e) {
    $conexion->rollback();
    echo "Error al registrar la membresía: " . $e->getMessage();
}
?>
