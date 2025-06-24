<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener planes
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");

// Obtener planes adicionales
$adicionales = $conexion->query("SELECT * FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Agregar Membresía</title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h2 { text-align: center; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; background: #222; color: gold; border: 1px solid gold; }
        button { margin-top: 20px; background: gold; color: black; padding: 12px; font-weight: bold; border: none; cursor: pointer; width: 100%; }
    </style>
</head>
<body>
<h2>Agregar Nueva Membresía</h2>

<form method="POST" action="guardar_membresia.php">
    <label>Buscar cliente (DNI, nombre o RFID):</label>
    <input type="text" id="busqueda_cliente" placeholder="Escriba para buscar...">

    <label>Seleccionar cliente:</label>
    <select name="cliente_id" id="cliente_id" required>
        <option value="">Seleccione un cliente</option>
    </select>

    <label>Seleccionar plan:</label>
    <select name="plan_id" id="plan_id" required onchange="actualizarDatosPlan()">
        <option value="">Seleccione un plan</option>
        <?php while ($p = $planes->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>" data-clases="<?= $p['dias_disponibles'] ?>" data-duracion="<?= $p['duracion'] ?>">
                <?= $p['nombre'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Clases disponibles:</label>
    <input type="number" id="clases_disponibles" name="clases_disponibles" readonly>

    <label>Fecha de inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" required onchange="calcularVencimiento()">

    <label>Fecha de vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly>

    <label>Otros pagos ($):</label>
    <input type="number" name="otros_pagos" id="otros_pagos" value="0" oninput="calcularTotal()">

    <label>Planes adicionales:</label>
    <?php while ($a = $adicionales->fetch_assoc()): ?>
        <label><input type="checkbox" name="adicionales[]" value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>" onchange="calcularTotal()"> <?= $a['nombre'] ?> ($<?= $a['precio'] ?>)</label>
    <?php endwhile; ?>

    <label>Método de pago:</label>
    <select name="metodo_pago" required>
        <option value="efectivo">Efectivo</option>
        <option value="transferencia">Transferencia</option>
        <option value="cuenta_corriente">Cuenta corriente</option>
        <option value="tarjeta">Tarjeta</option>
    </select>

    <label>Total a pagar:</label>
    <input type="number" name="total" id="total" readonly>

    <button type="submit">Registrar Membresía</button>
</form>

<script>
function actualizarDatosPlan() {
    const plan = document.getElementById('plan_id');
    const selected = plan.options[plan.selectedIndex];
    document.getElementById('clases_disponibles').value = selected.dataset.clases || 0;
    calcularVencimiento();
    calcularTotal();
}

function calcularVencimiento() {
    const fechaInicio = document.getElementById('fecha_inicio').value;
    const plan = document.getElementById('plan_id');
    const duracion = parseInt(plan.options[plan.selectedIndex]?.dataset.duracion || 0);

    if (fechaInicio && duracion > 0) {
        const inicio = new Date(fechaInicio);
        inicio.setMonth(inicio.getMonth() + duracion);
        const venc = inicio.toISOString().split('T')[0];
        document.getElementById('fecha_vencimiento').value = venc;
    }
}

function calcularTotal() {
    const plan = document.getElementById('plan_id');
    const precio = parseFloat(plan.options[plan.selectedIndex]?.dataset.precio || 0);
    const otros = parseFloat(document.getElementById('otros_pagos').value || 0);
    let adicionales = 0;

    document.querySelectorAll('input[name="adicionales[]"]:checked').forEach(el => {
        adicionales += parseFloat(el.dataset.precio || 0);
    });

    const total = precio + otros + adicionales;
    document.getElementById('total').value = total.toFixed(2);
}
</script>
</body>
</html>
