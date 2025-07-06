<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) {
    die('Acceso no autorizado.');
}

$id_gimnasio = $_SESSION['id_gimnasio'];
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Si se solicita exportar como CSV
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="asistencias_' . $fecha_filtro . '.csv"');
    $output = fopen("php://output", "w");
    fputcsv($output, ['Apellido', 'Nombre', 'DNI', 'Fecha y Hora']);

    $sql = "SELECT c.apellido, c.nombre, c.dni, a.fecha_hora
            FROM asistencias a
            JOIN clientes c ON a.cliente_id = c.id
            WHERE DATE(a.fecha_hora) = ? AND a.id_gimnasio = ?
            ORDER BY a.fecha_hora DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $fecha_filtro, $id_gimnasio);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Consulta para mostrar en pantalla
$sql = "SELECT c.apellido, c.nombre, c.dni, a.fecha_hora
        FROM asistencias a
        JOIN clientes c ON a.cliente_id = c.id
        WHERE DATE(a.fecha_hora) = ? AND a.id_gimnasio = ?
        ORDER BY a.fecha_hora DESC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("si", $fecha_filtro, $id_gimnasio);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">
    <meta charset="UTF-8">
    <title>Historial de Asistencias con Filtro</title>
</head>
<body>
    <div class="contenedor">
    <h2>Historial de Asistencias</h2>
    <form method="get">
        <label for="fecha">Seleccionar fecha:</label>
        <input type="date" name="fecha" id="fecha" value="<?php echo $fecha_filtro; ?>">
        <input type="submit" value="Filtrar">
        <a href="?fecha=<?php echo $fecha_filtro; ?>&exportar=csv" class="exportar">📤 Exportar CSV</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Fecha y Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['apellido']); ?></td>
                <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                <td><?php echo htmlspecialchars($row['dni']); ?></td>
                <td><?php echo htmlspecialchars($row['fecha_hora']); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
</body>
</html>
