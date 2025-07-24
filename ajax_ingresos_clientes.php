<?php
session_start();
include 'conexion.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date('Y-m-d');

$res = $conexion->query("
    SELECT c.apellido, m.clases_disponibles, m.fecha_vencimiento, a.hora
    FROM asistencias a
    INNER JOIN clientes c ON a.cliente_id = c.id
    LEFT JOIN membresias m ON m.cliente_id = c.id
        AND m.gimnasio_id = $gimnasio_id
        AND m.fecha_vencimiento = (
            SELECT MAX(fecha_vencimiento)
            FROM membresias
            WHERE cliente_id = c.id AND gimnasio_id = $gimnasio_id
        )
    WHERE a.fecha = '$hoy' AND a.gimnasio_id = $gimnasio_id
    ORDER BY a.hora DESC
");

while ($row = $res->fetch_assoc()) {
    echo "<tr>
        <td>{$row['apellido']}</td>
        <td>{$row['hora']}</td>
        <td>" . ($row['clases_disponibles'] ?? 'N/D') . "</td>
        <td>" . ($row['fecha_vencimiento'] ?? 'N/D') . "</td>
    </tr>";
}
