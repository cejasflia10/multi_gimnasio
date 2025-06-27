<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
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
$profesor_id = $_GET['profesor_id'] ?? '';
$exportar = isset($_GET['exportar']) && $_GET['exportar'] === 'csv';

$sql = "SELECT p.apellido, p.nombre, pg.mes, pg.horas_trabajadas, pg.monto_hora, pg.total_pagado
        FROM pagos_profesor pg
        JOIN profesores p ON pg.profesor_id = p.id
        WHERE pg.id_gimnasio = ? AND pg.mes = ?
        " . ($profesor_id != '' ? "AND pg.profesor_id = ?" : "") . "
        ORDER BY p.apellido, p.nombre";

$stmt = ($profesor_id != '') 
    ? $conexion->prepare($sql) 
    : $conexion->prepare(str_replace("AND pg.profesor_id = ?", "", $sql));

if ($profesor_id != '') {
    $stmt->bind_param("isi", $id_gimnasio, $mes, $profesor_id);
} else {
    $stmt->bind_param("is", $id_gimnasio, $mes);
}

$stmt->execute();
$resultado = $stmt->get_result();

if ($exportar) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pagos_profesores_' . $mes . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Apellido', 'Nombre', 'Mes', 'Horas Trabajadas', 'Monto por Hora', 'Total Pagado']);

    while ($row = $resultado->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos a Profesores</title>
    <style>
        body { background-color: #111; color: #fff; font-family: Arial, sans-serif; padding: 30px; }
        table { width: 100%; border-collapse: collapse; background-color: #222; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background-color: #333; color: #ffc107; }
        h2 { color: #ffc107; }
        input, select {
            padding: 8px; margin: 5px 0; font-size: 14px; width: 100%; max-width: 300px;
        }
        input[type="submit"], .exportar {
            background-color: #ffc107; border: none; color: #000; font-weight: bold; padding: 8px;
            text-decoration: none; display: inline-block; margin-top: 10px;
        }
    </style>
</head>
<body>
    <h2>Pagos Realizados a Profesores</h2>
    <form method="get">
        <label for="mes">Filtrar por mes:</label>
        <input type="month" name="mes" id="mes" value="<?php echo $mes; ?>">

        <label for="profesor_id">Filtrar por ID profesor (opcional):</label>
        <input type="number" name="profesor_id" id="profesor_id" value="<?php echo $profesor_id; ?>">

        <input type="submit" value="Filtrar">
        <a class="exportar" href="?mes=<?php echo $mes; ?>&profesor_id=<?php echo $profesor_id; ?>&exportar=csv">📤 Exportar CSV</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Mes</th>
                <th>Horas Trabajadas</th>
                <th>Monto por Hora</th>
                <th>Total Pagado</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['apellido']); ?></td>
                <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                <td><?php echo htmlspecialchars($row['mes']); ?></td>
                <td><?php echo htmlspecialchars($row['horas_trabajadas']); ?></td>
                <td>$<?php echo htmlspecialchars(number_format($row['monto_hora'], 2)); ?></td>
                <td>$<?php echo htmlspecialchars(number_format($row['total_pagado'], 2)); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>
