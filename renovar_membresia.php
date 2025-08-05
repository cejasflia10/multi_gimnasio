<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$id = intval($_GET['id'] ?? 0);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$membresia = $conexion->query("SELECT * FROM membresias WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
if (!$membresia) die("Membresía no encontrada.");

$cliente_id = $membresia['cliente_id'];
$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id")->fetch_assoc();
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");

// 🔹 Traer adicionales desde la base de datos
$adicionales = $conexion->query("SELECT * FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id ORDER BY nombre ASC");
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

    <form action="guardar_renovacion.php" method="POST">
        <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
        <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">

        <p><strong>Cliente:</strong> <?= htmlspecialchars($cliente['apellido'] . ', ' . $cliente['nombre']) ?></p>

        <label>Seleccionar Plan:</label>
        <select name="plan_id" id="plan_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while($plan = $planes->fetch_assoc()): ?>
                <option value="<?= $plan['id'] ?>"
                    data-precio="<?= $plan['precio'] ?>"
                    data-clases="<?= $plan['clases_disponibles'] ?>"
                    data-duracion="<?= $plan['duracion_meses'] ?>">
                    <?= htmlspecialchars($plan['nombre']) ?> - $<?= number_format($plan['precio'], 2) ?>
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
        <input type="number" name="otros_pagos" id="otros_pagos" value="0" step="0.01">

        <h3>Descuento:</h3>
        <label><input type="radio" name="descuento" value="0" checked> Sin descuento</label>
        <label><input type="radio" name="descuento" value="10"> 10%</label>
        <label><input type="radio" name="descuento" value="15"> 15%</label>
        <label><input type="radio" name="descuento" value="25"> 25%</label>
        <label><input type="radio" name="descuento" value="50"> 50%</label>

        <h3>Adicionales:</h3>
        <?php if ($adicionales->num_rows > 0): ?>
            <?php while ($ad = $adicionales->fetch_assoc()): ?>
                <label>
                    <input type="checkbox" class="adicional" value="<?= $ad['precio'] ?>">
                    <?= htmlspecialchars($ad['nombre']) ?> - 💰 $<?= number_format($ad['precio'], 2) ?>
                </label><br>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No hay adicionales configurados para este gimnasio.</p>
        <?php endif; ?>

        <input type="hidden" name="duracion_meses" id="duracion_meses" value="1">

        <h3>Formas de Pago</h3>
        <label>Efectivo:</label>
        <input type="number" name="pago_efectivo" id="pago_efectivo" value="0" step="0.01">

        <label>Transferencia:</label>
        <input type="number" name="pago_transferencia" id="pago_transferencia" value="0" step="0.01">

        <label>Débito:</label>
        <input type="number" name="pago_debito" id="pago_debito" value="0" step="0.01">

        <label>Crédito:</label>
        <input type="number" name="pago_credito" id="pago_credito" value="0" step="0.01">

        <label>Cuenta Corriente:</label>
        <input type="number" name="pago_cuenta_corriente" id="pago_cuenta_corriente" value="0" step="0.01">

        <h3 id="total_pagado">💰 Total a Pagar: $0.00</h3>

        <br><br>
        <button type="submit" class="boton-verde">Guardar Renovación</button>
        <a href="ver_membresias.php" class="boton-volver">Cancelar</a>
    </form>
</div>

<script>
function actualizarDatosPlan() {
    const plan = document.getElementById('plan_id').selectedOptions[0];
    if (!plan) return;

    const precio = parseFloat(plan.dataset.precio || 0);
    const clases = parseInt(plan.dataset.clases || 0);
    const duracion = parseInt(plan.dataset.duracion || 1);

    document.getElementById('precio').value = precio;
    document.getElementById('clases_disponibles').value = clases;
    document.getElementById('duracion_meses').value = duracion;

    const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
    if (!isNaN(fechaInicio)) {
        const fechaVenc = new Date(fechaInicio);
        fechaVenc.setMonth(fechaVenc.getMonth() + duracion);
        const yyyy = fechaVenc.getFullYear();
        const mm = String(fechaVenc.getMonth() + 1).padStart(2, '0');
        const dd = String(fechaVenc.getDate()).padStart(2, '0');
        document.getElementById('fecha_vencimiento').value = `${yyyy}-${mm}-${dd}`;
    }

    actualizarTotal();
}

function actualizarTotal() {
    const precio = parseFloat(document.getElementById('precio').value) || 0;
    const otrosPagos = parseFloat(document.getElementById('otros_pagos').value) || 0;

    let adicionales = 0;
    document.querySelectorAll('.adicional:checked').forEach(chk => {
        adicionales += parseFloat(chk.value);
    });

    const descuento = parseFloat(document.querySelector('input[name="descuento"]:checked').value) || 0;
    const subtotal = precio + otrosPagos + adicionales;
    const total = subtotal - (subtotal * descuento / 100);

    document.getElementById("total_pagado").textContent = "💰 Total a Pagar: $" + total.toFixed(2);
}

document.getElementById('plan_id').addEventListener('change', actualizarDatosPlan);
document.getElementById('fecha_inicio').addEventListener('change', actualizarDatosPlan);
document.getElementById('otros_pagos').addEventListener('input', actualizarTotal);
document.querySelectorAll('input[name="descuento"]').forEach(r => r.addEventListener('change', actualizarTotal));
document.querySelectorAll('.adicional').forEach(chk => chk.addEventListener('change', actualizarTotal));
</script>
</body>
</html>
