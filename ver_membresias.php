<?php
include 'conexion.php';
include 'menu.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Membresías Registradas</title>
    <style>
        body {
            background-color: #111;
            color: #ffc107;
            font-family: Arial, sans-serif;
            margin: 0;
            padding-top: 60px;
        }
        table {
            width: 95%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            padding: 12px;
            border: 1px solid #444;
            text-align: center;
        }
        th {
            background-color: #333;
            color: #ffc107;
        }
        td {
            color: #eee;
        }
        h1 {
            text-align: center;
            color: #ffc107;
        }
    </style>
</head>
<body>
    <h1>Membresías registradas</h1>
    <table>
        <tr>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Método de pago</th>
            <th>Monto abonado</th>
            <th>Clases totales</th>
            <th>Clases usadas</th>
            <th>Clases restantes</th>
        </tr>

<?php
$sql = "
SELECT 
    m.id,
    c.nombre, c.apellido,
    p.nombre AS plan_nombre, p.duracion, p.precio, p.cantidad_clases,
    m.fecha_inicio, m.fecha_vencimiento,
    m.metodo_pago, m.monto_pago,
    (SELECT COUNT(*) FROM clases_realizadas cr WHERE cr.membresia_id = m.id) AS clases_usadas
FROM membresias m
JOIN clientes c ON m.cliente_id = c.id
JOIN planes p ON m.plan_id = p.id
ORDER BY m.fecha_inicio DESC
";

$resultado = $conexion->query($sql);

while ($row = $resultado->fetch_assoc()) {
    $cliente = htmlspecialchars($row['apellido'] . ' ' . $row['nombre']);
    $plan = htmlspecialchars($row['plan_nombre'] . ' (' . $row['duracion'] . ')');
    $inicio = htmlspecialchars($row['fecha_inicio']);
    $vencimiento = htmlspecialchars($row['fecha_vencimiento']);
    $metodo = !empty($row['metodo_pago']) ? ucfirst($row['metodo_pago']) : 'No especificado';
    $monto = is_numeric($row['monto_pago']) ? '$' . number_format($row['monto_pago'], 2) : '$0.00';
    $clases_totales = (int)$row['cantidad_clases'];
    $clases_usadas = (int)$row['clases_usadas'];
    $clases_restantes = $clases_totales - $clases_usadas;
    
    echo "<tr>
        <td>$cliente</td>
        <td>$plan</td>
        <td>$inicio</td>
        <td>$vencimiento</td>
        <td>$metodo</td>
        <td>$monto</td>
        <td>$clases_totales</td>
        <td>$clases_usadas</td>
        <td>$clases_restantes</td>
    </tr>";
}
?>

    </table>
</body>
</html>
