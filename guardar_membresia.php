<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cliente_id = $_POST['cliente_id'];
$fecha_inicio = $_POST['fecha_inicio'];
$plan_id = $_POST['plan_id'];
$adicional_id = $_POST['adicional_id'] ?? null;
$otros_pagos = $_POST['otros_pagos'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$fecha_vencimiento = date('Y-m-d', strtotime($fecha_inicio . ' +1 month'));

$stmt = $conexion->prepare("INSERT INTO membresias (cliente_id, fecha_inicio, fecha_vencimiento, plan_id, adicional_id, otros_pagos, total, id_gimnasio) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

$total = 0;

// Obtener precio del plan
$plan = $conexion->query("SELECT precio FROM planes WHERE id = $plan_id")->fetch_assoc();
$total += $plan['precio'];

// Obtener precio del adicional
if ($adicional_id) {
    $adicional = $conexion->query("SELECT precio FROM planes_adicionales WHERE id = $adicional_id")->fetch_assoc();
    $total += $adicional['precio'];
}

$total += floatval($otros_pagos);

$stmt->bind_param("isssiddi", $cliente_id, $fecha_inicio, $fecha_vencimiento, $plan_id, $adicional_id, $otros_pagos, $total, $gimnasio_id);

if ($stmt->execute()) {
    header("Location: ver_membresias.php?mensaje=Membresía registrada");
} else {
    echo "Error: " . $stmt->error;
}
?>