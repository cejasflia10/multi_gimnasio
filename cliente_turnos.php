<?php
include 'conexion.php';
if (!isset($_GET['dni'])) {
    die("Acceso inválido.");
}
$dni = $_GET['dni'];

// Validar si el cliente tiene membresía activa
$cliente = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'")->fetch_assoc();
$id_cliente = $cliente['id'];

$membresia = $conexion->query("SELECT * FROM membresias 
    WHERE id_cliente = $id_cliente 
    AND fecha_vencimiento >= CURDATE() 
    AND clases_disponibles > 0 
    ORDER BY id DESC LIMIT 1");

if ($membresia->num_rows === 0) {
    die("<h2 style='color:orange;'>No tienes una membresía activa o sin clases disponibles</h2>");
}

// Mostrar turnos disponibles
$query = "
SELECT t.id, d.nombre AS dia, h.rango AS horario, CONCAT(p.apellido, ' ', p.nombre) AS profesor, t.cupo_maximo,
(SELECT COUNT(*) FROM reservas WHERE id_turno = t.id) AS reservados
FROM turnos t
JOIN dias d ON t.id_dia = d.id
JOIN horarios h ON t.id_horario = h.id
JOIN profesores p ON t.id_profesor = p.id
ORDER BY d.id, h.id
";
$turnos = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reservar Turno</title>
    <style>
        body { background: #111; color: gold; font-family: Arial; padding: 20px; }
        h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; background: #222; margin-top: 20px; }
        th, td { border: 1px solid #444; padding: 10px; text-align: center; }
        a.button { background: gold; color: #000; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Turnos Disponibles</h2>
    <table>
        <tr>
            <th>Día</th>
            <th>Horario</th>
            <th>Profesor</th>
            <th>Reservados</th>
            <th>Cupo</th>
            <th>Acción</th>
        </tr>
        <?php while ($row = $turnos->fetch_assoc()) {
            $disponible = $row['reservados'] < $row['cupo_maximo'];
            echo "<tr>";
            echo "<td>{$row['dia']}</td>";
            echo "<td>{$row['horario']}</td>";
            echo "<td>{$row['profesor']}</td>";
            echo "<td>{$row['reservados']}</td>";
            echo "<td>{$row['cupo_maximo']}</td>";
            echo "<td>";
            if ($disponible) {
                echo "<a class='button' href='reservar_cliente.php?dni=$dni&id_turno={$row['id']}'>Reservar</a>";
            } else {
                echo "Completo";
            }
            echo "</td></tr>";
        } ?>
    </table>
</body>
</html>
