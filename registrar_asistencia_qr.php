<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$dni = $_POST['dni'] ?? '';

if ($dni == '') {
    echo "<p style='color: yellow;'>DNI no recibido.</p>";
    exit;
}

// Aseguramos que DNI sea numérico
$dni = intval($dni);

// Consulta para obtener cliente con membresía activa y clases disponibles
$query = "
SELECT c.id AS cliente_id, c.nombre, c.apellido, m.id AS membresia_id, m.clases_disponibles, m.fecha_vencimiento 
FROM clientes c 
JOIN membresias m ON c.id = m.cliente_id 
WHERE c.dni = $dni 
  AND m.fecha_vencimiento >= CURDATE()
  AND m.clases_disponibles > 0 
ORDER BY m.fecha_vencimiento DESC 
LIMIT 1
";

$resultado = $conexion->query($query);

if ($resultado && $resultado->num_rows > 0) {
    $cliente = $resultado->fetch_assoc();
    $cliente_id = $cliente['cliente_id'];
    $membresia_id = $cliente['membresia_id'];
    $nombre = $cliente['nombre'];
    $apellido = $cliente['apellido'];
    $clases_disponibles = $cliente['clases_disponibles'] - 1;

    // Descontar clase
    $conexion->query("UPDATE membresias SET clases_disponibles = $clases_disponibles WHERE id = $membresia_id");

    // Registrar asistencia
    $conexion->query("INSERT INTO asistencias_clientes (cliente_id, fecha_hora) VALUES ($cliente_id, NOW())");

    echo "<p style='color: lightgreen;'>✅ Asistencia registrada: $nombre $apellido</p>";
    echo "<p style='color: lightgreen;'>📅 Vencimiento: {$cliente['fecha_vencimiento']} | Clases restantes: $clases_disponibles</p>";

} else {
    echo "<p style='color: gold;'>⚠️ El DNI $dni no tiene una membresía activa o clases disponibles.</p>";
}
?>
