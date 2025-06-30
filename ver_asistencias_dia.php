<?php
include 'conexion.php';
$hoy = date('Y-m-d');

$profesores_q = $conexion->query("
    SELECT p.apellido, p.nombre, a.hora_entrada, a.hora_salida 
    FROM asistencias_profesor a
    JOIN profesores p ON a.profesor_id = p.id
    WHERE a.fecha = '$hoy'
");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias del Día</title>
    <style>
        body { background: #000; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid gold; padding: 8px; text-align: center; }
        th { background: #111; }
    </style>
</head>
<body>
    <h1>Asistencias del Día - <?php echo date('d/m/Y'); ?></h1>
    <h2>Profesores</h2>
    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Ingreso</th>
            <th>Egreso</th>
        </tr>
        <?php
        if ($profesores_q->num_rows > 0) {
            while ($row = $profesores_q->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['apellido']}</td>
                        <td>{$row['nombre']}</td>
                        <td>{$row['hora_entrada']}</td>
                        <td>{$row['hora_salida']}</td>
                    </tr>";
            }
        } else {
            echo "<tr><td colspan='4'>Sin registros</td></tr>";
        }
        ?>
    </table>
</body>
</html>
