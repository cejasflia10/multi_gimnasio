<?php
include 'conexion.php';

$edad = isset($_GET['edad']) ? intval($_GET['edad']) : 0;
$sexo = $_GET['sexo'] ?? '';

if ($edad > 0 && ($sexo == 'masculino' || $sexo == 'femenino')) {
    $stmt = $conexion->prepare("
        SELECT id, nombre, peso_min, peso_max
        FROM categorias_evento
        WHERE genero = ? AND ? BETWEEN edad_min AND edad_max
        ORDER BY peso_min ASC
    ");
    $stmt->bind_param("si", $sexo, $edad);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        echo "<option value='{$row['id']}'>{$row['nombre']} ({$row['peso_min']}kg - {$row['peso_max']}kg)</option>";
    }
} else {
    echo "<option value=''>Seleccione edad y sexo</option>";
}
