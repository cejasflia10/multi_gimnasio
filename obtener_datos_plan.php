
<?php
include("conexion.php");

if (isset($_GET['plan_id'])) {
    $plan_id = intval($_GET['plan_id']);
    $query = "SELECT precio, duracion, clases_disponibles FROM planes WHERE id = $plan_id";
    $resultado = $conexion->query($query);
    if ($fila = $resultado->fetch_assoc()) {
        echo json_encode([
            'precio' => $fila['precio'],
            'duracion' => $fila['duracion'],
            'clases_disponibles' => $fila['clases_disponibles']
        ]);
    } else {
        echo json_encode(['error' => 'Plan no encontrado']);
    }
} else {
    echo json_encode(['error' => 'ID no especificado']);
}
?>
