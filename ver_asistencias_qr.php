<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
include 'conexion.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias QR</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
        }
        table {
            margin: 20px auto;
            border-collapse: collapse;
            width: 95%;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        td {
            background-color: #111;
        }
    </style>
</head>
<body>
    <h1>Asistencias de Hoy</h1>
    <table>
        <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Disciplina</th>
            <th>Clases Restantes</th>
            <th>Vencimiento</th>
        </tr>

        <?php
        $query = "
        SELECT a.fecha, a.hora, c.apellido, c.nombre, d.nombre AS disciplina,
               m.clases_disponibles, m.fecha_vencimiento
        FROM asistencias a
        INNER JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN disciplinas d ON c.disciplina_id = d.id
        LEFT JOIN membresias m ON m.id = (
            SELECT id FROM membresias
            WHERE cliente_id = c.id
            ORDER BY fecha_vencimiento DESC
            LIMIT 1
        )
        WHERE DATE(a.fecha) = CURDATE()
        AND c.gimnasio_id = ?
        ORDER BY a.hora DESC";

        $stmt = $conexion->prepare($query);
        $stmt->bind_param("i", $gimnasio_id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows == 0) {
            echo "<tr><td colspan='7'>No hay asistencias registradas hoy.</td></tr>";
        } else {
            while ($fila = $resultado->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $fila['fecha'] . "</td>";
                echo "<td>" . $fila['hora'] . "</td>";
                echo "<td>" . htmlspecialchars($fila['apellido']) . "</td>";
                echo "<td>" . htmlspecialchars($fila['nombre']) . "</td>";
                echo "<td>" . ($fila['disciplina'] ?? 'Sin asignar') . "</td>";
                echo "<td>" . ($fila['clases_disponibles'] ?? '-') . "</td>";
                echo "<td>" . ($fila['fecha_vencimiento'] ?? '-') . "</td>";
                echo "</tr>";
            }
        }

        $stmt->close();
        $conexion->close();
        ?>
    </table>
</body>
</html>
