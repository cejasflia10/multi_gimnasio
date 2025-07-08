<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
$clientes = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
$clientes_array = [];
while ($c = $clientes->fetch_assoc()) {
    $clientes_array[] = $c;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">

</head>

<script src="fullscreen.js">
// Calcular automáticamente al cargar la página
window.addEventListener('DOMContentLoaded', () => {
    calcularTotal();
});

function actualizarTotalVisible() {
    const total = document.getElementById('total_pagar');
    const span = document.getElementById('total_visible');
    if (total && span) {
        span.textContent = total.value;
    }
}
setInterval(actualizarTotalVisible, 500);
</script>

<body>
<div class="contenedor">
<?php if (isset($_GET['exito']) && $_GET['exito'] == 1): ?>
<div style="background-color: #0f0; color: black; padding: 10px; text-align: center; font-weight: bold; border-radius: 6px;">
    ✅ Membresía cargada correctamente
</div>
<script>
    setTimeout(() => {
        window.location.href = "nueva_membresia.php";
    }, 2500);
</script>
<?php endif; ?>

    
<div class="container">
    <h1>Registrar Nueva Membresía</h1>
    <form method="POST" action="guardar_membresia.php" onsubmit="calcularTotal()">
        <label>Buscar Cliente (DNI, nombre o apellido):</label>
        <input type="text" id="buscador_cliente" list="clientes" required oninput="buscarCliente()">
        <input type="hidden" name="cliente_id" id="cliente_id">
        <datalist id="clientes">
            <?php foreach ($clientes_array as $c): ?>
                <option data-id="<?= $c['id'] ?>" value="<?= $c['apellido'] ?>, <?= $c['nombre'] ?> (<?= $c['dni'] ?>)"></option>
            <?php endforeach; ?>
        </datalist>

        <label>Plan:</label>
        <select name="plan_id" id="plan" required onchange="cargarDatosPlan()">
            <option value="">Seleccionar plan</option>
            <?php foreach ($planes as $p): ?>
                <option value="<?= $p['id'] ?>"
                        data-precio="<?= $p['precio'] ?>"
                        data-clases="<?= $p['clases_disponibles'] ?>"
                        data-duracion="<?= $p['duracion_meses'] ?>">
                    <?= $p['nombre'] ?>
                </option>
            <?php endforeach; ?>
        </select>
<div style="max-height: 500px; overflow-y: auto;">
    <table>
        <!-- contenido -->
    </table>
</div>

        <label>Precio del Plan:</label>
        <input type="text" name="precio" id="precio" readonly>

        <label>Clases Disponibles:</label>
        <input type="number" name="clases_disponibles" id="clases_disponibles" readonly>

        <label>Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" id="fecha_inicio" required value="<?= date('Y-m-d') ?>" onchange="calcularVencimiento()">

        <label>Fecha de Vencimiento:</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly>

        <label>Planes Adicionales:</label>
        <div id="lista_adicionales">
            <?php foreach ($adicionales as $a): ?>
                <input type="checkbox" name="adicionales[]" value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>" onchange="calcularTotal()">
                <?= $a['nombre'] ?> ($<?= $a['precio'] ?>)<br>
            <?php endforeach; ?>
        </div>

        <label>Otros Pagos:</label>
        <input type="number" name="otros_pagos" id="otros_pagos" value="0" oninput="calcularTotal()">

        <label>Descuento:</label>
        <select id="descuento" name="descuento" onchange="calcularTotal()">
            <option value="0">Sin descuento</option>
            <option value="10">10%</option>
            <option value="15">15%</option>
            <option value="25">25%</option>
            <option value="50">50%</option>
        </select>

        <label>Total a Pagar:</label>
        <input type="text" name="total_pagar" id="total_pagar" readonly>
<p style="margin-top:5px; color: gold;">Total actual: <span id="total_visible" style="font-weight: bold;"></span></p>

        
<h3>💳 Formas de Pago</h3>
<div>
    <label>💵 Efectivo: </label>
    <input type="number" step="0.01" min="0" name="pago_efectivo" value="0"><br>

    <label>🏦 Transferencia: </label>
    <input type="number" step="0.01" min="0" name="pago_transferencia" value="0"><br>

    <label>💳 Débito: </label>
    <input type="number" step="0.01" min="0" name="pago_debito" value="0"><br>

    <label>💳 Crédito: </label>
    <input type="number" step="0.01" min="0" name="pago_credito" value="0"><br>

    <label>📒 Cuenta Corriente (Deuda): </label>
    <input type="number" step="0.01" min="0" name="pago_cuenta_corriente" value="0"><br>
</div>

<h4>Total abonado: $<span id="total_abonado">0.00</span></h4>

<script>
function actualizarTotal() {
    const efectivo = parseFloat(document.querySelector('[name=pago_efectivo]').value) || 0;
    const transferencia = parseFloat(document.querySelector('[name=pago_transferencia]').value) || 0;
    const debito = parseFloat(document.querySelector('[name=pago_debito]').value) || 0;
    const credito = parseFloat(document.querySelector('[name=pago_credito]').value) || 0;
    const cuenta_corriente = parseFloat(document.querySelector('[name=pago_cuenta_corriente]').value) || 0;

    const total = efectivo + transferencia + debito + credito + cuenta_corriente;
    document.getElementById('total_abonado').innerText = total.toFixed(2);
}

document.querySelectorAll('input[type=number]').forEach(input => {
    input.addEventListener('input', actualizarTotal);
});
</script>

        <button type="submit">Guardar Membresía</button>
   <script>
function validarPagos() {
    const total_plan = parseFloat(document.querySelector('[name=precio_plan]').value) || 0;

    const efectivo = parseFloat(document.querySelector('[name=pago_efectivo]').value) || 0;
    const transferencia = parseFloat(document.querySelector('[name=pago_transferencia]').value) || 0;
    const debito = parseFloat(document.querySelector('[name=pago_debito]').value) || 0;
    const credito = parseFloat(document.querySelector('[name=pago_credito]').value) || 0;
    const cuenta_corriente = parseFloat(document.querySelector('[name=pago_cuenta_corriente]').value) || 0;

    const total_pagado = efectivo + transferencia + debito + credito + cuenta_corriente;

    if (total_pagado < total_plan) {
        const diferencia = total_plan - total_pagado;
        return confirm(`⚠️ Se pagaron $${total_pagado.toFixed(2)} de $${total_plan.toFixed(2)}.\nSe registrará una deuda de $${diferencia.toFixed(2)} en cuenta corriente. ¿Desea continuar?`);
    }

    if (total_pagado > total_plan) {
        alert(`❌ El total abonado ($${total_pagado.toFixed(2)}) supera el precio del plan ($${total_plan.toFixed(2)}). Corrija los valores.`);
        return false;
    }

    return true;
}
</script>

    </form>

</div>

<script>
    const clientes = <?= json_encode($clientes_array) ?>;

    function buscarCliente() {
        const input = document.getElementById('buscador_cliente').value.toLowerCase();
        const cliente = clientes.find(c => `${c.apellido}, ${c.nombre} (${c.dni})`.toLowerCase() === input);
        document.getElementById('cliente_id').value = cliente ? cliente.id : '';
    }

    function cargarDatosPlan() {
        let plan = document.getElementById('plan');
        let selected = plan.options[plan.selectedIndex];
        let precio = selected.getAttribute('data-precio');
        let clases = selected.getAttribute('data-clases');
        let duracion = selected.getAttribute('data-duracion');

        document.getElementById('precio').value = precio;
        document.getElementById('clases_disponibles').value = clases;
        calcularVencimiento();
        calcularTotal();
    }

    function calcularVencimiento() {
        let plan = document.getElementById('plan');
        let duracion = plan.options[plan.selectedIndex]?.getAttribute('data-duracion');
        let fechaInicio = document.getElementById('fecha_inicio').value;
        if (!duracion || !fechaInicio) return;

        let fecha = new Date(fechaInicio);
        fecha.setMonth(fecha.getMonth() + parseInt(duracion));

        let mes = (fecha.getMonth() + 1).toString().padStart(2, '0');
        let dia = fecha.getDate().toString().padStart(2, '0');
        let anio = fecha.getFullYear();

        document.getElementById('fecha_vencimiento').value = `${anio}-${mes}-${dia}`;
    }

    function calcularTotal() {
        let precioPlan = parseFloat(document.getElementById('precio').value) || 0;
        let otros = parseFloat(document.getElementById('otros_pagos').value) || 0;
        let descuento = parseFloat(document.getElementById('descuento').value) || 0;
        let totalAdicionales = 0;
        document.querySelectorAll('#lista_adicionales input[type="checkbox"]:checked').forEach(cb => {
            totalAdicionales += parseFloat(cb.getAttribute('data-precio')) || 0;
        });

        let totalBruto = precioPlan + totalAdicionales + otros;
        let totalFinal = totalBruto - (totalBruto * descuento / 100);

        document.getElementById('total_pagar').value = totalFinal.toFixed(2);
    }
</script>
</body>
</html>
