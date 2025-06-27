<?php
session_start();
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}
if (!isset($_SESSION['id_gimnasio'])) {
    die('Acceso no autorizado.');
}

$id_gimnasio = $_SESSION['id_gimnasio'];
$mes = $_GET['mes'] ?? date('Y-m');

$sql = "SELECT p.nombre, p.apellido, 
               DATE(a.hora_entrada) as dia, 
               SEC_TO_TIME(SUM(TIMESTAMPDIFF(SECOND, a.hora_entrada, a.hora_salida))) as horas_totales
        FROM asistencias_profesor a
        JOIN profesores p ON a.profesor_id = p.id
        WHERE a.id_gimnasio = ? AND a.hora_salida IS NOT NULL AND DATE_FORMAT(a.hora_entrada, '%Y-%m') = ?
        GROUP BY p.id, dia
        ORDER BY p.apellido, dia";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("is", $id_gimnasio, $mes);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Horas Trabajadas Profesores</title>
    <style>
        body { background-color: #111; color: #fff; font-family: Arial, sans-serif; padding: 30px; }
        table { width: 100%; border-collapse: collapse; background-color: #222; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background-color: #333; color: #ffc107; }
        h2 { color: #ffc107; }
        input[type="month"], input[type="submit"] {
            padding: 8px; margin: 10px 0; font-size: 14px;
            background-color: #ffc107; border: none; color: #000;
        }
    </style>
</head>
<body>
    <h2>Reporte de Horas Trabajadas por Profesor</h2>
    <form method="get">
        <label for="mes">Seleccionar mes:</label>
        <input type="month" name="mes" id="mes" value="<?php echo $mes; ?>">
        <input type="submit" value="Filtrar">
    </form>

    <table>
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Día</th>
                <th>Horas trabajadas</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['apellido']); ?></td>
                <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                <td><?php echo htmlspecialchars($row['dia']); ?></td>
                <td><?php echo htmlspecialchars($row['horas_totales']); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>
