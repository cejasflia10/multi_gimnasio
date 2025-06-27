<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = intval($_POST['cliente_id']);
$gimnasio_id = intval($_POST['gimnasio_id']);
$plan_id = intval($_POST['plan_id']);
$precio = floatval($_POST['precio']);
$clases_disponibles = intval($_POST['clases_disponibles']);
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_vencimiento = $_POST['fecha_vencimiento'];
$otros_pagos = floatval($_POST['otros_pagos']);
$forma_pago = $_POST['forma_pago'];
$total = floatval($_POST['total']);
$duracion_meses = intval($_POST['duracion_meses']);

// 1. Mover membresía anterior al historial
$anterior = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");

if ($anterior && $anterior->num_rows > 0) {
    while ($m = $anterior->fetch_assoc()) {
        $conexion->query("INSERT INTO membresias_historial 
            (cliente_id, gimnasio_id, plan_id, precio, clases_disponibles, fecha_inicio, fecha_vencimiento, otros_pagos, forma_pago, total, duracion_meses) 
            VALUES (
                {$m['cliente_id']}, {$m['gimnasio_id']}, {$m['plan_id']}, {$m['precio']}, {$m['clases_disponibles']},
                '{$m['fecha_inicio']}', '{$m['fecha_vencimiento']}', {$m['otros_pagos']}, '{$m['forma_pago']}', {$m['total']}, {$m['duracion_meses']}
            )");
    }
}

// 2. Eliminar membresía anterior
$conexion->query("DELETE FROM membresias WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");

// 3. Insertar nueva membresía
$query = "INSERT INTO membresias 
    (cliente_id, gimnasio_id, plan_id, precio, clases_disponibles, fecha_inicio, fecha_vencimiento, otros_pagos, forma_pago, total, duracion_meses)
    VALUES 
    ($cliente_id, $gimnasio_id, $plan_id, $precio, $clases_disponibles, '$fecha_inicio', '$fecha_vencimiento', $otros_pagos, '$forma_pago', $total, $duracion_meses)";

if ($conexion->query($query)) {
    echo "<script>alert('Renovación realizada correctamente'); window.location='ver_membresias.php';</script>";
} else {
    echo "Error al renovar: " . $conexion->error;
}
