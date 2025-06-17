<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cliente_id = $_POST['cliente_id'] ?? null;
    $plan_id = $_POST['plan_id'] ?? null;
    $adicional_id = !empty($_POST['adicional_id']) ? $_POST['adicional_id'] : null;
    $disciplina_id = !empty($_POST['disciplina_id']) ? $_POST['disciplina_id'] : null;
    $profesor_id = !empty($_POST['profesor_id']) ? $_POST['profesor_id'] : null;
    $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');

    $metodo = $_POST['metodo_pago'] ?? '';
    $monto_pago = $_POST['monto_pago'] ?? 0;

    $monto_efectivo = $_POST['monto_efectivo'] ?? ($metodo === 'Efectivo' ? $monto_pago : 0);
    $monto_transferencia = $_POST['monto_transferencia'] ?? ($metodo === 'Transferencia' ? $monto_pago : 0);
    $total = $_POST['total'] ?? $monto_pago;

    if (!$cliente_id || !$plan_id) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios.']);
        exit;
    }

    $stmt = $conexion->prepare("INSERT INTO membresias 
        (cliente_id, plan_id, adicional_id, disciplina_id, profesor_id, fecha_inicio, monto_efectivo, monto_transferencia, total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "iiiiissdd",
        $cliente_id,
        $plan_id,
        $adicional_id,
        $disciplina_id,
        $profesor_id,
        $fecha_inicio,
        $monto_efectivo,
        $monto_transferencia,
        $total
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Membresía guardada correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
}
?>
