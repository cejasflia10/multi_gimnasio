<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$id = intval($_GET['id'] ?? 0);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$membresia = $conexion->query("SELECT * FROM membresias WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
$cliente_id = $membresia['cliente_id'];
$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id")->fetch_assoc();
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Renovar Membresía</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="contenedor">
    <h2>♻️ Renovar Membresía</h2>

    <form action="guardar_renovacion.php" method="POST" onsubmit="return validarFormulario()">
        <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
        <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">

        <p><strong>Cliente:</strong> <?= $cliente['apellido'] . ', ' . $cliente['nombre'] ?></p>

        <label>Seleccionar Plan:</label>
        <select name="plan_id" id="plan_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while($plan = $planes->fetch_assoc()): ?>
                <option value="<?= $plan['id'] ?>"
                    data-precio="<?= $plan['precio'] ?>"
                    data-clases="<?= $plan['clases_disponibles'] ?>"
                    data-duracion="<?= $plan['duracion_meses'] ?>">
                    <?= $plan['nombre'] ?> - $<?= $plan['precio'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= date('Y-m-d') ?>" required>

        <label>Fecha de Vencimiento:</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly required>

        <label>Clases Disponibles:</label>
        <input type="number" name="clases_disponibles" id="clases_disponibles" readonly required>

        <label>Precio:</label>
        <input type="number" name="precio" id="precio" readonly required>

        <label>Otros Pagos:</label>
        <input type="number" name="otros_pagos" id="otros_pagos" value="0">

        <label>Descuento:</label>
        <input type="number" name="descuento" id="descuento" value="0">

        <input type="hidden" name="duracion_meses" id="duracion_meses" value="1">

        <h3>Formas de Pago</h3>
        <label>Efectivo:</label>
        <input type="number" name="pago_efectivo" id="pago_efectivo" value="0">

        <label>Transferencia:</label>
        <input type="number" name="pago_transferencia" id="pago_transferencia" value="0">

        <label>Débito:</label>
        <input type="number" name="pago_debito" id="pago_debito" value="0">

        <label>Crédito:</label>
        <input type="number" name="pago_credito" id="pago_credito" value="0">

        <label>Cuenta Corriente:</label>
        <input type="number" name="pago_cuenta_corriente" id="pago_cuenta_corriente" value="0">

        <br><br>
        <button type="submit" class="boton-verde">Guardar Renovación</button>
        <a href="ver_membresias.php" class="boton-volver">Cancelar</a>
    </form>
</div>

<script>
document.getElementById('plan_id').addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    const precio = parseFloat(selected.getAttribute('data-precio'));
    const clases = parseInt(selected.getAttribute('data-clases'));
    const duracion = parseInt(selected.getAttribute('data-duracion'));

    document.getElementById('precio').value = precio || 0;
    document.getElementById('clases_disponibles').value = clases || 0;
    document.getElementById('duracion_meses').value = duracion || 1;

    const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
    const fechaVencimiento = new Date(fechaInicio);
    fechaVencimiento.setMonth(fechaVencimiento.getMonth() + duracion);

    const yyyy = fechaVencimiento.getFullYear();
    const mm = String(fechaVencimiento.getMonth() + 1).padStart(2, '0');
    const dd = String(fechaVencimiento.getDate()).padStart(2, '0');
    document.getElementById('fecha_vencimiento').value = `${yyyy}-${mm}-${dd}`;
});
</script>
</body>
</html>
