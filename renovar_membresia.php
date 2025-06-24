<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID no especificado.");
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener la membresía anterior
$query = $conexion->prepare("SELECT * FROM membresias WHERE id = ? AND gimnasio_id = ?");
$query->bind_param("ii", $id, $gimnasio_id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    die("Membresía no encontrada.");
}

$anterior = $result->fetch_assoc();

// Desactivar la actual
$conexion->query("UPDATE membresias SET activa = 0 WHERE id = $id");

// Calcular nueva fecha de vencimiento (1 mes por defecto)
$inicio = date('Y-m-d');
$vencimiento = date('Y-m-d', strtotime("+1 month"));

// Insertar nueva membresía
$stmt = $conexion->prepare("INSERT INTO membresias (
    cliente_id, plan_id, fecha_inicio, fecha_vencimiento,
    clases_restantes, metodo_pago, otros_pagos, total,
    adicional_id, gimnasio_id, activa
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

$stmt->bind_param(
    "iississdii",
    $anterior['cliente_id'],
    $anterior['plan_id'],
    $inicio,
    $vencimiento,
    $anterior['clases_restantes'],
    $anterior['metodo_pago'],
    $anterior['otros_pagos'],
    $anterior['total'],
    $anterior['adicional_id'],
    $anterior['gimnasio_id']
);

if ($stmt->execute()) {
    echo "<script>alert('Membresía renovada correctamente'); window.location.href='ver_membresias.php';</script>";
} else {
    echo "<script>alert('Error al renovar: " . $stmt->error . "'); history.back();</script>";
}
?>
