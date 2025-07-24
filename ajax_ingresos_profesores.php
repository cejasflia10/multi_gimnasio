<?php
session_start();
include 'conexion.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date('Y-m-d');

$res = $conexion->query("
    SELECT p.apellido, ap.hora_entrada, ap.hora_salida
    FROM asistencias_profesores ap
    INNER JOIN profesores p ON ap.profesor_id = p.id
    WHERE ap.fecha = '$hoy' AND ap.gimnasio_id = $gimnasio_id
");

while ($row = $res->fetch_assoc()) {
    echo "<tr>
        <td>{$row['apellido']}</td>
        <td>{$row['hora_entrada']}</td>
        <td>" . ($row['hora_salida'] ?: '-') . "</td>
    </tr>";
}
