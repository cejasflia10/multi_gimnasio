<?php
include 'conexion.php';

$cliente_id = $_POST['cliente_id'];
$plan_id = $_POST['plan_id'];
$adicional_id = $_POST['adicional_id'] ?? null;
$fecha_inicio = $_POST['fecha_inicio'];
$disciplina_id = $_POST['disciplina_id'] ?? null;
$profesor_id = $_POST['profesor_id'] ?? null;
$metodos = $_POST['metodo_pago'];
$montos = $_POST['monto_pago'];
$detalles = $_POST['detalle_otro'];

// Validación
if (!$cliente_id || !$plan_id || !$fecha_inicio || empty($metodos) || empty($montos)) {
    die("Faltan datos obligatorios.");
}

// Insertar membresía
$stmt = $conexion->prepare("INSERT INTO membresias (cliente_id, plan_id, adicional_id, fecha_inicio, disciplina_id, profesor_id)
                            VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiisii", $cliente_id, $plan_id, $adicional_id, $fecha_inicio, $disciplina_id, $profesor_id);
$stmt->execute();
$id_membresia = $conexion->insert_id;

// Insertar pagos
for ($i = 0; $i < count($metodos); $i++) {
    $metodo = $metodos[$i];
    $monto = floatval($montos[$i]);
    $detalle = $metodo === "Otro" ? trim($detalles[$i]) : $metodo;

    $stmt_pago = $conexion->prepare("INSERT INTO pagos_membresia (membresia_id, metodo_pago, monto)
                                     VALUES (?, ?, ?)");
    $stmt_pago->bind_param("isd", $id_membresia, $detalle, $monto);
    $stmt_pago->execute();
}

echo "Membresía y pagos registrados correctamente.";
?>
