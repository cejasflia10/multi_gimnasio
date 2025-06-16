<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $plan_id = $_POST['plan_id'];
    $adicional_id = $_POST['adicional_id'] ?? null;
    $fecha_inicio = $_POST['fecha_inicio'];
    $metodo_pago = $_POST['metodo_pago'];

    // Obtener precio del plan
    $stmt = $conexion->prepare("SELECT precio FROM planes WHERE id = ?");
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $precio_plan = $fila ? $fila['precio'] : 0;

    // Obtener precio del adicional
    $precio_adicional = 0;
    if ($adicional_id) {
        $stmt = $conexion->prepare("SELECT precio FROM planes_adicionales WHERE id = ?");
        $stmt->bind_param("i", $adicional_id);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $fila = $resultado->fetch_assoc();
        $precio_adicional = $fila ? $fila['precio'] : 0;
    }

    $total = $precio_plan + $precio_adicional;

    // Calcular fecha de vencimiento (30 días desde inicio)
    $fecha_vencimiento = date('Y-m-d', strtotime($fecha_inicio . ' +30 days'));

    // Insertar en base de datos
    $stmt = $conexion->prepare("INSERT INTO membresias (cliente_id, plan_id, adicional_id, fecha_inicio, fecha_vencimiento, metodo_pago, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiisssd", $cliente_id, $plan_id, $adicional_id, $fecha_inicio, $fecha_vencimiento, $metodo_pago, $total);

    if ($stmt->execute()) {
        echo "<script>alert('Membresía registrada exitosamente'); window.location.href = 'nueva_membresia.php';</script>";
    } else {
        echo "<script>alert('Error al registrar la membresía'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido'); window.history.back();</script>";
}
?>
