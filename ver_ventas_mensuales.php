<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Filtros por GET
$categoria = $_GET['categoria'] ?? '';
$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

// Consulta SQL
$sql = "
    SELECT v.id, v.fecha, v.tipo, v.monto, v.producto, v.precio_venta,
           c.apellido, c.nombre
    FROM ventas v
    JOIN clientes c ON v.gimnasio_id = c.gimnasio_id AND v.producto IS NOT NULL AND v.gimnasio_id = $gimnasio_id
    WHERE MONTH(v.fecha) = $mes AND YEAR(v.fecha) = $anio
";

if ($categoria !== '') {
    $categoria = $conexion->real_escape_string($categoria);
    $sql .= " AND v.tipo = '$categoria'";
}

$sql .= " ORDER BY v.fecha DESC";
$ventas = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas Mensuales</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>🧾 Ventas del Mes</h2>

    <form method="GET" style="margin-bottom: 15px;">
        <label>Mes:</label>
        <select name="mes">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= $m ?></option>
            <?php endfor; ?>
        </select>

        <label>Año:</label>
        <select name="anio">
            <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
                <option value="<?= $y ?>" <?= $anio == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <label>Tipo:</label>
        <select name="categoria">
            <option value="">-- Todas --</option>
            <option value="proteccion" <?= $categoria === 'proteccion' ? 'selected' : '' ?>>Protección</option>
            <option value="indumentaria" <?= $categoria === 'indumentaria' ? 'selected' : '' ?>>Indumentaria</option>
            <option value="suplemento" <?= $categoria === 'suplemento' ? 'selected' : '' ?>>Suplemento</option>
        </select>

        <button type="submit">Filtrar</button>
    </form>

    <table class="tabla">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Producto</th>
                <th>Tipo</th>
                <th>Precio Unitario</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_mes = 0;
            while ($row = $ventas->fetch_assoc()):
                $total_mes += $row['monto'];
            ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></td>
                <td><?= $row['apellido'] . ' ' . $row['nombre'] ?></td>
                <td><?= $row['producto'] ?></td>
                <td><?= ucfirst($row['tipo']) ?></td>
                <td>$<?= number_format($row['precio_venta'], 2) ?></td>
                <td>$<?= number_format($row['monto'], 2) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <h3>Total seleccionado: $<?= number_format($total_mes, 2) ?></h3>
</div>
</body>
</html>
