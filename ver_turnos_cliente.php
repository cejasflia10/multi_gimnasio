<?php
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['dni'])) {
    die("Acceso inválido.");
}
$dni = $_GET['dni'];

// Obtener ID del cliente
$cliente = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'")->fetch_assoc();
$id_cliente = $cliente['id'];

// Consultar turnos reservados
$query = "
SELECT d.nombre AS dia, h.rango AS horario, CONCAT(p.apellido, ' ', p.nombre) AS profesor, r.fecha_reserva
FROM reservas r
JOIN turnos t ON r.id_turno = t.id
JOIN dias d ON t.id_dia = d.id
JOIN horarios h ON t.id_horario = h.id
JOIN profesores p ON t.id_profesor = p.id
WHERE r.id_cliente = $id_cliente
ORDER BY r.fecha_reserva DESC
";
$reservas = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Turnos</title>
    <style>
        body { background: #111; color: gold; font-family: Arial; padding: 20px; }
        h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; background: #222; margin-top: 20px; }
        th, td { border: 1px solid #444; padding: 10px; text-align: center; }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>
    <h2>Mis Turnos Reservados</h2>
    <table>
        <tr>
            <th>Fecha de Reserva</th>
            <th>Día</th>
            <th>Horario</th>
            <th>Profesor</th>
        </tr>
        <?php while ($row = $reservas->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['fecha_reserva']}</td>";
            echo "<td>{$row['dia']}</td>";
            echo "<td>{$row['horario']}</td>";
            echo "<td>{$row['profesor']}</td>";
            echo "</tr>";
        } ?>
    </table>
</body>
</html>
