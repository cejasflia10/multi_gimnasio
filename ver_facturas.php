<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener facturas del gimnasio
$facturas = $conexion->query("SELECT * FROM facturas WHERE gimnasio_id = $gimnasio_id ORDER BY id DESC");
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
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Cliente ID</th>
                <th>Total ($)</th>
                <th>Método de Pago</th>
                <th>Detalle</th>
                <th>Opciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($f = $facturas->fetch_assoc()): ?>
            <tr>
                <td><?= $f['id'] ?></td>
                <td><?= $f['fecha_pago'] ?></td>
                <td><?= $f['cliente_id'] ?></td>
                <td><?= number_format($f['total'], 2) ?></td>
                <td><?= $f['metodo_pago'] ?></td>
                <td><?= $f['detalle'] ?></td>
                <td>
                    <a href="factura_pdf.php?id=<?= $f['id'] ?>" target="_blank">📄 Descargar</a> |
                    <a href="factura_pdf.php?id=<?= $f['id'] ?>&imprimir=1" target="_blank">🖨️ Imprimir</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
