
<?php
session_start();
include 'conexion.php';

$cliente_id = $_POST['cliente_id'];
$plan_id = $_POST['plan_id'];
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_vencimiento = $_POST['fecha_vencimiento'];
$clases_disponibles = $_POST['clases_disponibles'];
$adicional_id = isset($_POST['adicional_id']) && $_POST['adicional_id'] !== '' ? intval($_POST['adicional_id']) : null;
$otros_pagos = $_POST['otros_pagos'];
$total = $_POST['total'];
$metodo_pago = $_POST['metodo_pago'];
$gimnasio_id = isset($_SESSION['gimnasio_id']) ? $_SESSION['gimnasio_id'] : 0;

$stmt = $conexion->prepare("INSERT INTO membresias (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, adicional_id, otros_pagos, total, metodo_pago, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iissiiissi", $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_disponibles, $adicional_id, $otros_pagos, $total, $metodo_pago, $gimnasio_id);

if ($stmt->execute()) {
    echo "<script>alert('Membresía registrada correctamente'); window.location.href='membresias.php';</script>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conexion->close();
?>
