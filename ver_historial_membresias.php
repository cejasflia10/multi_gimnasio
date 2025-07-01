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
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2, h3 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #222;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #333;
        }
        .btn-volver {
            background-color: gold;
            color: #111;
            padding: 10px 20px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<script>
// Reactivar pantalla completa con el primer clic
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function entrarPantallaCompleta() {
        if (!document.fullscreenElement && body.requestFullscreen) {
            body.requestFullscreen().catch(err => {
                console.warn("No se pudo activar pantalla completa:", err);
            });
        }
    }

    // Activar pantalla completa al hacer clic
    body.addEventListener('click', entrarPantallaCompleta, { once: true });
});

// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones como F12, Ctrl+Shift+I
document.addEventListener('keydown', function (e) {
    if (
        e.key === "F12" ||
        (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J")) ||
        (e.ctrlKey && e.key === "U")
    ) {
        e.preventDefault();
    }
});
</script>

<body>

<h2>Historial de Membresías</h2>
<h3><?= htmlspecialchars($cliente['apellido'] . ', ' . $cliente['nombre']) ?> (DNI: <?= $cliente['dni'] ?>)</h3>
<a href="ver_membresias.php" class="btn-volver">← Volver</a>

<table>
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
            <td><?= $fila['precio'] ?></td>
            <td><?= $fila['clases_disponibles'] ?></td>
            <td><?= $fila['fecha_inicio'] ?></td>
            <td><?= $fila['fecha_vencimiento'] ?></td>
            <td><?= $fila['otros_pagos'] ?></td>
            <td><?= $fila['forma_pago'] ?></td>
            <td><?= $fila['total'] ?></td>
            <td><?= $fila['duracion_meses'] ?> mes(es)</td>
            <td><?= $fila['fecha_borrado'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
