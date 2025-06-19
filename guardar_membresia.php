<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cliente_id = $_POST["cliente_id"];
    $plan_id = $_POST["plan_id"];
    $fecha_inicio = $_POST["fecha_inicio"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $clases = $_POST["clases"];
    $monto_total = $_POST["total"];
    $metodo_pago = $_POST["metodo_pago"];
    $otros_pagos = $_POST["otros_pagos"];
    $adicional_id = $_POST["adicional_id"] ?? null;
    $gimnasio_id = $_SESSION["gimnasio_id"];

    $stmt = $conexion->prepare("INSERT INTO membresias (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_restantes, total, metodo_pago, adicional_id, monto_pago, id_gimnasio)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissidssdi", $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases, $monto_total, $metodo_pago, $adicional_id, $otros_pagos, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Membresía registrada correctamente'); window.location.href='membresias.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>