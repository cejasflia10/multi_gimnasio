<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$id = $_GET['id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$membresia = $conexion->query("SELECT * FROM membresias WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
$clientes = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>

<body>
<div class="contenedor">
<h2>✏️ Editar Membresía</h2>

<form action="guardar_edicion_membresia.php" method="POST">
    <input type="hidden" name="id" value="<?= $membresia['id'] ?>">

    <label>Cliente:</label>
    <select name="cliente_id" required>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $membresia['cliente_id'] ? 'selected' : '' ?>>
                <?= $c['apellido'] . ', ' . $c['nombre'] . ' (' . $c['dni'] . ')' ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Plan:</label>
    <select name="plan_id" id="plan_id" required>
        <?php
        $planesData = [];
        while ($p = $planes->fetch_assoc()):
            $selected = $p['id'] == $membresia['plan_id'] ? 'selected' : '';
            echo "<option value='{$p['id']}' $selected>{$p['nombre']}</option>";
            $planesData[$p['id']] = [
                'precio' => $p['precio'],
                'clases' => $p['clases_disponibles'],
                'duracion_meses' => $p['duracion_meses']
            ];
        endwhile;
        ?>
    </select>

    <label>Precio:</label>
    <input type="number" step="0.01" name="precio" id="precio" value="<?= $membresia['precio'] ?>" required>

    <label>Clases Disponibles:</label>
    <input type="number" name="clases_disponibles" id="clases_disponibles" value="<?= $membresia['clases_restantes'] ?>" required>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= $membresia['fecha_inicio'] ?>" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="<?= $membresia['fecha_vencimiento'] ?>" required>

    <label>Otros Pagos:</label>
    <input type="number" step="0.01" name="otros_pagos" value="<?= $membresia['otros_pagos'] ?>">

    <label>💵 Pago en Efectivo:</label>
    <input type="number" step="0.01" name="pago_efectivo" id="pago_efectivo" value="<?= $membresia['pago_efectivo'] ?? 0 ?>">

    <label>🏦 Transferencia:</label>
    <input type="number" step="0.01" name="pago_transferencia" id="pago_transferencia" value="<?= $membresia['pago_transferencia'] ?? 0 ?>">

    <label>💳 Débito:</label>
    <input type="number" step="0.01" name="pago_debito" id="pago_debito" value="<?= $membresia['pago_debito'] ?? 0 ?>">

    <label>💳 Crédito:</label>
    <input type="number" step="0.01" name="pago_credito" id="pago_credito" value="<?= $membresia['pago_credito'] ?? 0 ?>">

    <label>📂 Cuenta Corriente:</label>
    <input type="number" step="0.01" name="pago_cuenta_corriente" id="pago_cuenta_corriente" value="<?= $membresia['pago_cuenta_corriente'] ?? 0 ?>">

    <label>💰 Total:</label>
    <input type="number" step="0.01" name="total" id="total" value="<?= $membresia['total'] ?>" required>

    <button type="submit">💾 Guardar Cambios</button>
</form>

<br>
<a href="ver_membresias.php" style="color:#ffd600;">⬅ Volver al listado</a>

<script>
const planes = <?= json_encode($planesData) ?>;
const selectPlan = document.getElementById('plan_id');
const precio = document.getElementById('precio');
const clases = document.getElementById('clases_disponibles');
const fechaInicio = document.getElementById('fecha_inicio');
const fechaVencimiento = document.getElementById('fecha_vencimiento');

function actualizarCampos() {
    const id = selectPlan.value;
    const datos = planes[id];
    if (datos) {
        precio.value = datos.precio;
        clases.value = datos.clases;
        const inicio = new Date(fechaInicio.value);
        if (!isNaN(inicio)) {
            inicio.setMonth(inicio.getMonth() + parseInt(datos.duracion_meses));
            const vencimiento = inicio.toISOString().split('T')[0];
            fechaVencimiento.value = vencimiento;
        }
    }
}
selectPlan.addEventListener('change', actualizarCampos);

function recalcularTotal() {
    const efectivo = parseFloat(document.getElementById('pago_efectivo').value) || 0;
    const trans = parseFloat(document.getElementById('pago_transferencia').value) || 0;
    const debito = parseFloat(document.getElementById('pago_debito').value) || 0;
    const credito = parseFloat(document.getElementById('pago_credito').value) || 0;
    const cuenta = parseFloat(document.getElementById('pago_cuenta_corriente').value) || 0;
    const total = efectivo + trans + debito + credito + cuenta;
    document.getElementById('total').value = total.toFixed(2);
}

document.querySelectorAll('#pago_efectivo, #pago_transferencia, #pago_debito, #pago_credito, #pago_cuenta_corriente').forEach(input => {
    input.addEventListener('input', recalcularTotal);
});
</script>
</div>
</body>
</html>
