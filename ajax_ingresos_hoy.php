<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date('Y-m-d');

$res = $conexion->query("
    SELECT c.apellido, c.nombre, a.hora 
    FROM asistencias a 
    JOIN clientes c ON a.cliente_id = c.id 
    WHERE a.fecha = '$hoy' AND c.gimnasio_id = $gimnasio_id 
    ORDER BY a.hora DESC
");

if ($res->num_rows > 0) {
    echo "<ul>";
    while ($row = $res->fetch_assoc()) {
        echo "<li>✅ {$row['apellido']}, {$row['nombre']} - ⏰ {$row['hora']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:gray;'>No se registraron ingresos de alumnos hoy.</p>";
}
?>
