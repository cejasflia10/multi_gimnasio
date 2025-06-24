<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = intval($_POST['id']);
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    $plan_id = $_POST['plan_id'];
    $adicional_id = $_POST['adicional_id'] ?? null;
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $clases_restantes = $_POST['clases_restantes'];
    $otros_pagos = $_POST['otros_pagos'] ?? 0;
    $metodo_pago = $_POST['metodo_pago'];
    $total = $_POST['total'];

    // Verificar que la membresía pertenezca al gimnasio
    $ver = $conexion->prepare("SELECT id FROM membresias WHERE id = ? AND gimnasio_id = ?");
    $ver->bind_param("ii", $id, $gimnasio_id);
    $ver->execute();
    $ver->store_result();
    if ($ver->num_rows === 0) {
        echo "<script>alert('No se encontró la membresía o no pertenece a este gimnasio.'); history.back();</script>";
        exit;
    }
    $ver->close();

    // Actualizar datos
    $stmt = $conexion->prepare("UPDATE membresias SET 
        plan_id = ?, adicional_id = ?, fecha_inicio = ?, fecha_vencimiento = ?,
        clases_restantes = ?, metodo_pago = ?, otros_pagos = ?, total = ?
        WHERE id = ?");

    $stmt->bind_param("iississdi",
        $plan_id, $adicional_id, $fecha_inicio, $fecha_vencimiento,
        $clases_restantes, $metodo_pago, $otros_pagos, $total,
        $id
    );

    if ($stmt->execute()) {
        echo "<script>alert('Membresía actualizada correctamente'); window.location.href='ver_membresias.php';</script>";
    } else {
        echo "<script>alert('Error al actualizar: " . $stmt->error . "'); history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "<script>alert('Acceso inválido.'); history.back();</script>";
}
?>
