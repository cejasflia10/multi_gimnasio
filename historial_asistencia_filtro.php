<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    die('Acceso no autorizado.');
}

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');

// Si se solicita exportar como CSV
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="asistencias_' . $fecha_filtro . '.csv"');
    $output = fopen("php://output", "w");
    fputcsv($output, ['Apellido', 'Nombre', 'DNI', 'Fecha y Hora']);

    $sql = "SELECT c.apellido, c.nombre, c.dni, a.fecha_hora
            FROM asistencias a
            JOIN clientes c ON a.cliente_id = c.id
            WHERE DATE(a.fecha_hora) = ? AND a.gimnasio_id = ?
            ORDER BY a.fecha_hora DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $fecha_filtro, $gimnasio_id);
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
        WHERE DATE(a.fecha_hora) = ? AND a.gimnasio_id = ?
        ORDER BY a.fecha_hora DESC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("si", $fecha_filtro, $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Asistencias</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; }
        .contenedor { padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
        th { background: #222; color: gold; }
        tr:nth-child(even) { background: #111; }
        .exportar {
            background-color: gold;
            color: black;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 5px;
            margin-left: 10px;
        }
        .exportar:hover {
            background-color: orange;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>📅 Historial de Asistencias</h2>
    <form method="get">
        <label for="fecha">Seleccionar fecha:</label>
        <input type="date" name="fecha" id="fecha" value="<?= $fecha_filtro ?>">
        <input type="submit" value="Filtrar">
        <a href="?fecha=<?= $fecha_filtro ?>&exportar=csv" class="exportar">📤 Exportar CSV</a>
    </form>

    <?php if ($resultado->num_rows > 0): ?>
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
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['apellido']) ?></td>
                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                        <td><?= htmlspecialchars($row['dni']) ?></td>
                        <td><?= htmlspecialchars($row['fecha_hora']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: orange;">No hay asistencias registradas para esta fecha.</p>
    <?php endif; ?>
</div>
</body>
</html>
