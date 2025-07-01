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
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 650px;
            margin: auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-top: 10px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input, select, button, datalist {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 6px;
            border: none;
            font-size: 16px;
        }
        input[readonly] {
            background-color: #333;
            color: gold;
        }
        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            margin-top: 20px;
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
<div class="container">
    <h1>Registrar Nueva Membresía</h1>
    <form method="POST" action="guardar_membresia.php">
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

        <label>Método de Pago:</label>
        <select name="metodo_pago" required>
            <option value="">Seleccionar método</option>
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="cuenta_corriente">Cuenta Corriente</option>
            <option value="debito">Débito</option>
            <option value="credito">Crédito</option>
        </select>

        <button type="submit">Guardar Membresía</button>
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
