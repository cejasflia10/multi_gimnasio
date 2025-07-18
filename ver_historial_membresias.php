<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['cliente_id']) || !is_numeric($_GET['cliente_id'])) {
    die("ID de cliente no especificado.");
}

$cliente_id = intval($_GET['cliente_id']);

// Obtener datos del cliente
$cliente = $conexion->query("SELECT nombre, apellido, dni FROM clientes WHERE id = $cliente_id LIMIT 1");
if ($cliente->num_rows === 0) {
    die("Cliente no encontrado.");
}
$cliente = $cliente->fetch_assoc();

// Obtener historial de membresías
$query = "SELECT mh.*, p.nombre AS nombre_plan 
          FROM membresias_historial mh
          LEFT JOIN planes p ON mh.plan_id = p.id
          WHERE mh.cliente_id = $cliente_id
          ORDER BY mh.fecha_borrado DESC";
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h2>📜 Historial de Membresías</h2>
    <h3><?= htmlspecialchars($cliente['apellido'] . ', ' . $cliente['nombre']) ?> (DNI: <?= $cliente['dni'] ?>)</h3>

    <a href="ver_membresias.php" class="boton-volver">← Volver</a>

    <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Precio</th>
                    <th>Clases</th>
                    <th>Inicio</th>
                    <th>Vencimiento</th>
                    <th>Otros Pagos</th>
                    <th>Forma de Pago</th>
                    <th>Total</th>
                    <th>Duración</th>
                    <th>Fecha de Renovación</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($fila['nombre_plan']) ?></td>
                    <td>$<?= number_format($fila['precio'], 2, ',', '.') ?></td>
                    <td><?= $fila['clases_disponibles'] ?></td>
                    <td><?= $fila['fecha_inicio'] ?></td>
                    <td><?= $fila['fecha_vencimiento'] ?></td>
                    <td>$<?= number_format($fila['otros_pagos'], 2, ',', '.') ?></td>
                    <td><?= $fila['forma_pago'] ?></td>
                    <td>$<?= number_format($fila['total'], 2, ',', '.') ?></td>
                    <td><?= $fila['duracion_meses'] ?> mes(es)</td>
                    <td><?= $fila['fecha_borrado'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
