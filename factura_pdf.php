<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$filtro_mes = $_GET['mes'] ?? date('m');
$filtro_tipo = $_GET['tipo'] ?? 'todos';
$filtro_anio = date('Y');

$consultas = [];

if ($filtro_tipo == 'todos' || $filtro_tipo == 'productos') {
    $consultas[] = "
        SELECT f.id, f.fecha_pago, f.cliente_id,
            CONCAT(c.apellido, ' ', c.nombre) AS cliente,
            f.total,
            CASE 
                WHEN f.pago_efectivo > 0 THEN CONCAT('Efectivo: $', FORMAT(f.pago_efectivo, 2))
                WHEN f.pago_transferencia > 0 THEN CONCAT('Transferencia: $', FORMAT(f.pago_transferencia, 2))
                WHEN f.pago_debito > 0 THEN CONCAT('Débito: $', FORMAT(f.pago_debito, 2))
                WHEN f.pago_credito > 0 THEN CONCAT('Crédito: $', FORMAT(f.pago_credito, 2))
                WHEN f.pago_cuenta_corriente > 0 THEN CONCAT('Cuenta Corriente: $', FORMAT(f.pago_cuenta_corriente, 2))
                ELSE 'Sin datos'
            END AS metodo_pago,
            f.detalle,
            'productos' AS tipo
        FROM facturas f
        LEFT JOIN clientes c ON f.cliente_id = c.id
        WHERE MONTH(f.fecha_pago) = $filtro_mes AND YEAR(f.fecha_pago) = $filtro_anio AND f.gimnasio_id = $gimnasio_id
    ";
}

if ($filtro_tipo == 'todos' || $filtro_tipo == 'membresias') {
    $consultas[] = "
        SELECT m.id, m.fecha_inicio AS fecha_pago, m.cliente_id,
            CONCAT(c.apellido, ' ', c.nombre) AS cliente,
            (m.pago_efectivo + m.pago_transferencia + m.pago_debito + m.pago_credito + m.pago_cuenta_corriente) AS total,
            CASE 
                WHEN m.pago_efectivo > 0 THEN CONCAT('Efectivo: $', FORMAT(m.pago_efectivo, 2))
                WHEN m.pago_transferencia > 0 THEN CONCAT('Transferencia: $', FORMAT(m.pago_transferencia, 2))
                WHEN m.pago_debito > 0 THEN CONCAT('Débito: $', FORMAT(m.pago_debito, 2))
                WHEN m.pago_credito > 0 THEN CONCAT('Crédito: $', FORMAT(m.pago_credito, 2))
                WHEN m.pago_cuenta_corriente > 0 THEN CONCAT('Cuenta Corriente: $', FORMAT(m.pago_cuenta_corriente, 2))
                ELSE 'Sin datos'
            END AS metodo_pago,
            'Membresía' AS detalle,
            'membresia' AS tipo
        FROM membresias m
        LEFT JOIN clientes c ON m.cliente_id = c.id
        WHERE MONTH(m.fecha_inicio) = $filtro_mes AND YEAR(m.fecha_inicio) = $filtro_anio AND m.gimnasio_id = $gimnasio_id
    ";
}

$sql = implode(" UNION ALL ", $consultas) . " ORDER BY fecha_pago DESC";
$facturas = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Facturas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>📄 Facturas Generadas</h2>

    <form method="GET" style="margin-bottom: 20px;">
        <label>Mes:</label>
        <select name="mes">
            <?php
            for ($m = 1; $m <= 12; $m++) {
                $selected = ($filtro_mes == $m) ? 'selected' : '';
                printf("<option value='%02d' %s>%s</option>", $m, $selected, date("F", mktime(0, 0, 0, $m, 1)));
            }
            ?>
        </select>

        <label>Tipo:</label>
        <select name="tipo">
            <option value="todos" <?= $filtro_tipo === 'todos' ? 'selected' : '' ?>>Todos</option>
            <option value="productos" <?= $filtro_tipo === 'productos' ? 'selected' : '' ?>>Productos</option>
            <option value="membresias" <?= $filtro_tipo === 'membresias' ? 'selected' : '' ?>>Membresías</option>
        </select>

        <button type="submit">🔍 Filtrar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Total ($)</th>
                <th>Método de Pago</th>
                <th>Detalle</th>
                <th>Tipo</th>
                <th>Opciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($f = $facturas->fetch_assoc()): ?>
            <tr>
                <td><?= $f['fecha_pago'] ?></td>
                <td><?= $f['cliente'] ?></td>
                <td>$<?= number_format($f['total'], 2) ?></td>
                <td><?= $f['metodo_pago'] ?></td>
                <td><?= $f['detalle'] ?></td>
                <td><?= ucfirst($f['tipo']) ?></td>
                <td>
                    <a href="factura_pdf.php?id=<?= $f['id'] ?>&tipo=<?= $f['tipo'] ?>" target="_blank">📄 Descargar</a> |
                    <a href="factura_pdf.php?id=<?= $f['id'] ?>&tipo=<?= $f['tipo'] ?>&imprimir=1" target="_blank">🖨️ Imprimir</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
