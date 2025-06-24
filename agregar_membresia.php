<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener planes
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");

// Obtener clientes
$clientes = $conexion->query("SELECT id, apellido, nombre, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
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
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: gold;
        }
        label {
            display: block;
            margin-top: 15px;
        }
        input, select, button {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: none;
        }
        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            margin-top: 20px;
        }
        #resultados_busqueda {
            background: #222;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<h1>Agregar Membresía</h1>

<form action="guardar_membresia.php" method="POST">

    <label for="buscador_cliente">Buscar Cliente (DNI o Apellido):</label>
    <input type="text" id="buscador_cliente" placeholder="Escribí DNI o apellido..." onkeyup="buscarCliente()">
    
    <label for="cliente_id">Seleccionar Cliente:</label>
    <select name="cliente_id" id="cliente_id" required>
        <option value="">-- Seleccionar --</option>
        <?php foreach ($clientes_array as $c): ?>
            <option value="<?= $c['id'] ?>">
                <?= $c['apellido'] . ', ' . $c['nombre'] . ' (' . $c['dni'] . ')' ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="plan_id">Seleccionar Plan:</label>
    <select name="plan_id" id="plan_id" onchange="cargarDatosPlan(this.value)" required>
        <option value="">-- Seleccionar plan --</option>
        <?php while ($p = $planes->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <label for="precio">Precio del Plan:</label>
    <input type="number" name="precio" id="precio" readonly>

    <label for="clases_disponibles">Clases Disponibles:</label>
    <input type="number" name="clases_disponibles" id="clases_disponibles" readonly>

    <label for="fecha_inicio">Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" onchange="calcularVencimiento()" required>

    <label for="fecha_vencimiento">Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly>

    <input type="hidden" id="duracion_meses" name="duracion_meses">

    <label for="otros_pagos">Otros Pagos (adicionales):</label>
    <input type="number" name="otros_pagos" id="otros_pagos" value="0" oninput="actualizarTotal()">

    <label for="forma_pago">Forma de Pago:</label>
    <select name="forma_pago" id="forma_pago" onchange="actualizarTotal()" required>
        <option value="">-- Seleccionar --</option>
        <option value="efectivo">Efectivo</option>
        <option value="transferencia">Transferencia</option>
        <option value="debito">Tarjeta Débito</option>
        <option value="credito">Tarjeta Crédito</option>
        <option value="cuenta_corriente">Cuenta Corriente</option>
    </select>

    <label for="total">Monto Total:</label>
    <input type="number" name="total" id="total" readonly>

    <button type="submit">Registrar Membresía</button>
    <a href="index.php"><button type="button">Volver al Menú</button></a>
</form>

<script>
const clientes = <?= json_encode($clientes_array) ?>;

function buscarCliente() {
    const texto = document.getElementById('buscador_cliente').value.toLowerCase();
    const select = document.getElementById('cliente_id');
    select.innerHTML = '<option value="">-- Seleccionar --</option>';
    clientes.forEach(c => {
        const combinado = (c.apellido + ' ' + c.nombre + ' ' + c.dni).toLowerCase();
        if (combinado.includes(texto)) {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = `${c.apellido}, ${c.nombre} (${c.dni})`;
            select.appendChild(opt);
        }
    });
}

function cargarDatosPlan(planId) {
    if (!planId) return;

    fetch('obtener_datos_plan.php?plan_id=' + planId)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            document.getElementById('precio').value = data.precio;
            document.getElementById('clases_disponibles').value = data.clases_disponibles;
            document.getElementById('duracion_meses').value = data.duracion_meses;

            calcularVencimiento();
            actualizarTo
