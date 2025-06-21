<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST["id"];
    $fecha_inicio = $_POST["fecha_inicio"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $clases_disponibles = $_POST["clases_disponibles"];
    $plan_id = $_POST["plan_id"];
    $adicional_id = !empty($_POST["adicional_id"]) ? $_POST["adicional_id"] : null;
    $otros_pagos = isset($_POST["otros_pagos"]) && is_numeric($_POST["otros_pagos"]) ? $_POST["otros_pagos"] : 0;
    $metodo_pago = $_POST["metodo_pago"];
    $total = floatval($_POST["total"]);

    // Convertir a negativo si es cuenta corriente
    if ($metodo_pago === "Cuenta Corriente") {
        $total = -abs($total);
    }

    $gimnasio_id = $_SESSION['gimnasio_id'];

    $stmt = $conexion->prepare("UPDATE membresias SET 
        fecha_inicio = ?, 
        fecha_vencimiento = ?, 
        clases_disponibles = ?, 
        plan_id = ?, 
        adicional_id = ?, 
        otros_pagos = ?, 
        metodo_pago = ?, 
        total = ?
        WHERE id = ? AND gimnasio_id = ?");

    $stmt->bind_param("ssiisiisii", 
        $fecha_inicio, $fecha_vencimiento, $clases_disponibles, $plan_id,
        $adicional_id, $otros_pagos, $metodo_pago, $total, $id, $gimnasio_id
    );

    if ($stmt->execute()) {
        echo "<script>alert('Membresía actualizada correctamente.'); window.location.href='ver_membresias.php';</script>";
    } else {
        echo "Error al actualizar: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "Acceso no permitido.";
}
?>
