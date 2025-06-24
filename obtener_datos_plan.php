
<?php
include 'conexion.php';

if (!isset($_GET['plan_id'])) {
    echo json_encode(['error' => 'ID de plan no proporcionado']);
    exit;
}

$plan_id = intval($_GET['plan_id']);

$query = "SELECT precio, duracion, clases_disponibles FROM planes WHERE id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $plan = $resultado->fetch_assoc();
    echo json_encode([
        'precio' => $plan['precio'],
        'duracion' => $plan['duracion'],
        'disponibles' => $plan['clases_disponibles']
    ]);
} else {
    echo json_encode(['error' => 'Plan no encontrado']);
}
?>
