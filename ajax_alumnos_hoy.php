<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$alumnos_hoy = $conexion->query("
    SELECT c.apellido, c.nombre, a.hora 
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.fecha = CURDATE() AND c.gimnasio_id = $gimnasio_id
    ORDER BY a.hora
");

if ($alumnos_hoy->num_rows > 0) {
    echo "<ul>";
    while ($al = $alumnos_hoy->fetch_assoc()) {
        echo "<li>{$al['apellido']} {$al['nombre']} - ⏰ {$al['hora']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:gray;'>No se registraron ingresos de alumnos hoy.</p>";
}
