<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ventas_productos_mod.php");
    exit;
}

$total = floatval($_POST['total'] ?? 0);
$cliente_nombre = $_POST['cliente_nombre'] ?? '';
$cliente_temporal = isset($_POST['cliente_temporal']) ? 1 : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formas de Pago</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>💳 Formas de Pago</h2>

    <p><strong>Total original:</strong> $<span id="total_original"><?= number_format($total, 2) ?></span></p>

    <form method="POST" action="guardar_venta_productos.php" onsubmit="return validarPago();">
        <input type="hidden" name="cliente_nombre" value="<?= htmlspecialchars($cliente_nombre) ?>">
        <input type="hidden" name="cliente_temporal" value="<?= $cliente_temporal ?>">
        <input type="hidden" name="total_original" id="total_original_val" value="<?= $total ?>">

        <label for="descuento">Descuento:</label>
        <select id="descuento" name="descuento" onchange="recalcularTotal()" required>
            <option value="0">Sin descuento</option>
            <option value="10">10%</option>
            <option value="15">15%</option>
            <option value="20">20%</option>
        </select>

        <label>Pagos:</label>
        <input type="number" name="pago_efectivo" placeholder="💵 Efectivo" step="0.01" min="0">
        <input type="number" name="pago_transferencia" placeholder="🏦 Transferencia" step="0.01" min="0">
        <input type="number" name="pago_debito" placeholder="💳 Débito" step="0.01" min="0">
        <input type="number" name="pago_credito" placeholder="💳 Crédito" step="0.01" min="0">
        <input type="number" name="pago_cuenta_corriente" placeholder="📒 Cuenta Corriente (Deuda)" step="0.01" min="0">

        <p><strong>Total con descuento:</strong> $<span id="total_descuento"><?= number_format($total, 2) ?></span></p>
        <input type="hidden" name="total_con_descuento" id="total_con_descuento" value="<?= $total ?>">

        <button type="submit">✅ Finalizar y Generar Factura</button>
    </form>
</div>

<script>
function recalcularTotal() {
    const original = parseFloat(document.getElementById('total_original_val').value);
    const desc = parseFloat(document.getElementById('descuento').value);
    const total_desc = original - (original * (desc / 100));
    document.getElementById('total_descuento').textContent = total_desc.toFixed(2);
    document.getElementById('total_con_descuento').value = total_desc.toFixed(2);
}

function validarPago() {
    const total = parseFloat(document.getElementById('total_con_descuento').value);
    let suma = 0;
    document.querySelectorAll('input[type=number]').forEach(el => {
        if (!isNaN(el.value)) suma += parseFloat(el.value || 0);
    });

    if (Math.abs(suma - total) > 0.01) {
        alert("⚠️ El total ingresado ($" + suma.toFixed(2) + ") no coincide con el total a pagar ($" + total.toFixed(2) + ").");
        return false;
    }
    return true;
}
</script>
</body>
</html>
