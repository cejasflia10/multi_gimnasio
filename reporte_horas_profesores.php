<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$profesor_id = $_GET['profesor_id'] ?? '';

// Profesores disponibles
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id ORDER BY apellido");

// Query dinámico
$where = "WHERE a.gimnasio_id = $gimnasio_id AND MONTH(a.fecha) = $mes AND YEAR(a.fecha) = $anio";
if (!empty($profesor_id)) {
    $where .= " AND a.profesor_id = " . intval($profesor_id);
}

$query = "
    SELECT p.apellido, p.nombre, a.fecha, a.hora_ingreso, a.hora_egreso,
           TIMESTAMPDIFF(MINUTE, a.hora_ingreso, a.hora_egreso) AS minutos_trabajados
    FROM asistencias_profesor a
    JOIN profesores p ON a.profesor_id = p.id
    $where
    ORDER BY a.fecha DESC, p.apellido
";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Horas Profesores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
    <h2>📊 Reporte de Horas Trabajadas por Profesores</h2>
    <form method="get">
        <label>Mes:</label>
        <select name="mes">
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= $i ?>" <?= ($i == $mes ? 'selected' : '') ?>><?= $i ?></option>
            <?php endfor; ?>
        </select>
        <label>Año:</label>
        <input type="number" name="anio" value="<?= $anio ?>" style="width:80px;">
        <label>Profesor:</label>
        <select name="profesor_id">
            <option value="">Todos</option>
            <?php while ($prof = $profesores->fetch_assoc()): ?>
                <option value="<?= $prof['id'] ?>" <?= ($prof['id'] == $profesor_id ? 'selected' : '') ?>><?= $prof['apellido'] . ' ' . $prof['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
        <input type="submit" class="btn" value="Filtrar">
    </form>

    <table>
        <tr>
            <th>Profesor</th>
            <th>Fecha</th>
            <th>Ingreso</th>
            <th>Egreso</th>
            <th>Minutos Trabajados</th>
        </tr>
        <?php while($fila = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $fila['apellido'] . ' ' . $fila['nombre'] ?></td>
            <td><?= $fila['fecha'] ?></td>
            <td><?= $fila['hora_ingreso'] ?></td>
            <td><?= $fila['hora_egreso'] ?></td>
            <td><?= $fila['minutos_trabajados'] ?> min</td>
        </tr>
        <?php endwhile; ?>
    </table>
    </div>

</body>
</html>
