<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
include 'conexion.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #111; color: #FFD700; font-family: Arial; margin: 0; padding: 20px; }
        h1 { text-align: center; }
        form { max-width: 600px; margin: auto; background: #222; padding: 20px; border-radius: 8px; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; background: #333; color: #FFD700; border: 1px solid #FFD700; border-radius: 4px; }
        .grupo { margin-bottom: 15px; }
        .boton { background: #FFD700; color: #000; font-weight: bold; cursor: pointer; }
        .acciones { text-align: center; margin-top: 20px; }
        .acciones button, .acciones a { margin: 5px; padding: 10px 15px; background: #FFD700; color: #000; border: none; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
<h1>Nueva Membresía</h1>
<form method="POST" action="guardar_membresia.php">
    <div class="grupo">
        <label for="busqueda_cliente">Buscar Cliente (DNI, Nombre, RFID):</label>
        <input type="text" id="busqueda_cliente" placeholder="Escriba DNI, nombre o RFID">
        <input type="hidden" name="cliente_id" id="cliente_id">
        <div id="datos_cliente" style="margin-top: 10px;"></div>
    </div>

    <div class="grupo">
        <label for="plan_id">Seleccionar Plan:</label>
        <select name="plan_id" id="plan_id" required>
            <option value="">Seleccione un plan</option>
<?php
$planes = $conexion->query("SELECT id, nombre, clases, duracion_dias, precio FROM planes WHERE gimnasio_id = $gimnasio_id");
while ($plan = $planes->fetch_assoc()) {
    echo "<option value='{$plan['id']}' data-clases='{$plan['clases']}' data-dias='{$plan['duracion_dias']}' data-precio='{$plan['precio']}'>{$plan['nombre']} ({$plan['clases']} clases - \${$plan['precio']})</option>";
}
?>
        </select>
    </div>

    <div class="grupo">
        <label for="fecha_inicio">Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
    </div>

    <div class="grupo">
        <label for="fecha_vencimiento">Fecha de Vencimiento:</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly>
    </div>

    <div class="grupo">
        <label for="clases_disponibles">Clases Disponibles:</label>
        <input type="number" name="clases_disponibles" id="clases_disponibles" readonly>
    </div>

    <div class="grupo">
        <label for="metodo_pago">Método de Pago:</label>
        <select name="metodo_pago" id="metodo_pago" required>
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="tarjeta_debito">Tarjeta Débito</option>
            <option value="tarjeta_credito">Tarjeta Crédito</option>
            <option value="cuenta_corriente">Cuenta Corriente</option>
        </select>
    </div>

    <div class="grupo">
        <label for="adicionales">Planes Adicionales:</label>
        <select name="adicionales[]" id="adicionales" multiple>
<?php
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
while ($extra = $adicionales->fetch_assoc()) {
    echo "<option value='{$extra['id']}' data-precio='{$extra['precio']}'>{$extra['nombre']} (\${$extra['precio']})</option>";
}
?>
        </select>
    </div>

    <div class="grupo">
        <label for="otros_pagos">Otros Pagos (Descripción y Monto):</label>
        <input type="text" name="otros_descripcion" placeholder="Ej: Matrícula">
        <input type="number" name="otros_monto" step="0.01" placeholder="Monto">
    </div>

    <div class="grupo">
        <label for="total">Total a Pagar:</label>
        <input type="text" id="total" name="total" readonly>
    </div>

    <div class="grupo">
        <button type="submit" class="boton">Registrar Membresía</button>
    </div>
</form>

<div class="acciones">
    <a href="index.php">Volver al Panel</a>
    <a href="membresias.php">Ver Membresías</a>
</div>

<script>
document.getElementById('plan_id').addEventListener('change', function () {
    const option = this.options[this.selectedIndex];
    const clases = option.getAttribute('data-clases');
    const dias = option.getAttribute('data-dias');
    const precio = parseFloat(option.getAttribute('data-precio')) || 0;
    const fechaInicio = document.getElementById('fecha_inicio').value;

    document.getElementById('clases_disponibles').value = clases;

    if (fechaInicio) {
        const fecha = new Date(fechaInicio);
        fecha.setDate(fecha.getDate() + parseInt(dias));
        document.getElementById('fecha_vencimiento').value = fecha.toISOString().split('T')[0];
    }

    calcularTotal();
});

document.getElementById('adicionales').addEventListener('change', calcularTotal);
document.getElementById('fecha_inicio').addEventListener('change', () => {
    document.getElementById('plan_id').dispatchEvent(new Event('change'));
});

function calcularTotal() {
    let total = 0;

    const planPrecio = parseFloat(document.querySelector('#plan_id option:checked')?.getAttribute('data-precio')) || 0;
    total += planPrecio;

    const seleccionados = document.querySelectorAll('#adicionales option:checked');
    seleccionados.forEach(opt => total += parseFloat(opt.getAttribute('data-precio')) || 0);

    const otros = parseFloat(document.querySelector('input[name="otros_monto"]').value) || 0;
    total += otros;

    if (document.getElementById('metodo_pago').value === 'cuenta_corriente') {
        total *= -1;
    }

    document.getElementById('total').value = total.toFixed(2);
}
</script>
</body>
</html>