<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');

$reservas = $conexion->query("
    SELECT rc.dia_semana, rc.hora_inicio, rc.fecha_reserva,
           c.nombre, c.apellido,
           CONCAT(p.apellido, ' ', p.nombre) AS profesor
    FROM reservas_clientes rc
    JOIN clientes c ON rc.cliente_id = c.id
    JOIN profesores p ON rc.profesor_id = p.id
    WHERE rc.fecha_reserva = '$fecha_filtro'
      AND rc.gimnasio_id = $gimnasio_id
    ORDER BY rc.hora_inicio
");

if ($reservas && $reservas->num_rows > 0) {
    while ($r = $reservas->fetch_assoc()) {
        echo "<li>📅 {$r['dia_semana']} - 🕒 {$r['hora_inicio']}<br>👤 {$r['apellido']} {$r['nombre']}<br>👨‍🏫 {$r['profesor']}</li>";
    }
} else {
    echo "<li style='color:gray;'>No hay reservas registradas para este día.</li>";
}
