<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Filtros
$tipo = $_GET['tipo'] ?? '';
$fecha_desde = $_GET['desde'] ?? '';
$fecha_hasta = $_GET['hasta'] ?? '';
$cliente = $_GET['cliente'] ?? '';

$condiciones = "WHERE gimnasio_id = $gimnasio_id";

if ($tipo != '') {
    $condiciones .= " AND tipo = '$tipo'";
}
if ($fecha_desde != '' && $fecha_hasta != '') {
    $condiciones .= " AND fecha BETWEEN '$fecha_desde' AND '$fecha_hasta'";
}
if ($cliente != '') {
    $condiciones .= " AND cliente_nombre LIKE '%$cliente%'";
}

$query = "SELECT * FROM ventas_productos $condiciones ORDER BY fecha DESC, hora DESC";
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Ventas</title>
    <style>
        body { background-color: #000; color: gold; font-family: sans-serif; padding: 20px; }
        input, select, button { padding: 6px; margin: 5px; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #444; text-align: center; }
        a.factura { color: lightgreen; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<h2>📋 Ventas Registradas</h2>

<form method="GET">
    <label>Tipo:</label>
    <select name="tipo">
        <option value="">Todos</option>
        <option value="Suplemento" <?= $tipo == "Suplemento" ? 'selected' : '' ?>>Suplementos</option>
        <option value="Protección" <?= $tipo == "Protección" ? 'selected' : '' ?>>Protecciones</option>
        <option value="Indumentaria" <?= $tipo == "Indumentaria" ? 'selected' : '' ?>>Indumentaria</option>
        <option value="Producto" <?= $tipo == "Producto" ? 'selected' : '' ?>>Otros productos</option>
    </select>

    <label>Desde:</label>
    <input type="date" name="desde" value="<?= $fecha_desde ?>">
    <label>Hasta:</label>
    <input type="date" name="hasta" value="<?= $fecha_hasta ?>">

    <label>Cliente:</label>
    <input type="text" name="cliente" placeholder="Nombre o DNI" value="<?= htmlspecialchars($cliente) ?>">

    <button type="submit">Filtrar</button>
</form>

<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Cliente</th>
            <th>Tipo</th>
            <th>Total</th>
            <th>Pagos</th>
            <th>Factura</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($v = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $v['fecha'] ?></td>
            <td><?= $v['hora'] ?></td>
            <td><?= $v['cliente_nombre'] ?></td>
            <td><?= $v['tipo'] ?? 'Producto' ?></td>
            <td>$<?= number_format($v['total'], 2) ?></td>
            <td>
                <?= $v['efectivo'] > 0 ? "Efectivo $" . $v['efectivo'] . "<br>" : "" ?>
                <?= $v['transferencia'] > 0 ? "Transf $" . $v['transferencia'] . "<br>" : "" ?>
                <?= $v['debito'] > 0 ? "Débito $" . $v['debito'] . "<br>" : "" ?>
                <?= $v['credito'] > 0 ? "Crédito $" . $v['credito'] . "<br>" : "" ?>
                <?= $v['cuenta_corriente'] > 0 ? "<b>Deuda</b> $" . $v['cuenta_corriente'] : "" ?>
            </td>
            <td>
                <?php
                $archivo = "facturas/factura_venta_" . $v['id'] . ".pdf";
                if (file_exists($archivo)):
                ?>
                <a href="<?= $archivo ?>" target="_blank" class="factura">📄 Ver</a>
                <?php else: ?>
                <span style="color: red;">❌</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</body>
</html>
