<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

if (!isset($_SESSION['gimnasio_id'])) {
    $_SESSION['gimnasio_id'] = 1; // Para pruebas fuerza el gimnasio_id
}
$gimnasio_id = $_SESSION['gimnasio_id'];
$hoy = date('Y-m-d');

// DEBUG: Mostramos el gimnasio y fecha
echo "<p style='color:lime;'>Gimnasio: $gimnasio_id | Fecha: $hoy</p>";

// Consulta directa
$query = "
SELECT c.apellido, c.nombre, a.fecha, a.hora
FROM asistencias a
INNER JOIN clientes c ON c.id = a.cliente_id
WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$hoy'
ORDER BY a.hora DESC
";

$resultado = $conexion->query($query);
if (!$resultado) {
    die("<p style='color:red;'>Error en la consulta: " . $conexion->error . "</p>");
}

// Conteo de resultados
if ($resultado->num_rows === 0) {
    echo "<p style='color:yellow;'>Sin resultados para hoy.</p>";
} else {
    echo "<table border='1' style='width:100%; color:white; background:black;'>";
    echo "<tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>";
    while ($row = $resultado->fetch_assoc()) {
        echo "<tr>
            <td>{$row['apellido']}</td>
            <td>{$row['nombre']}</td>
            <td>{$row['fecha']}</td>
            <td>{$row['hora']}</td>
        </tr>";
    }
    echo "</table>";
}
?>
