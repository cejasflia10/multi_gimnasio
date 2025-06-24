<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$clientes = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
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
            font-weight: bold;
        }

        input, select, button {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: none;
            font-size: 16px;
        }

        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            margin-top: 20px;
            cursor: pointer;
        }

        #cliente_id option {
            color: black;
        }
    </style>
</head>
<body>

<h1>Agregar Membresía</h1>

<form action="guardar_membresia.php" method="POST">

    <label>Buscar Cliente (DNI o Apellido):</label>
    <input type="text" id="buscador" onkeyup="filtrarClientes()" placeholder="Ej: 24533 o GARCIA">

    <label>Seleccionar Cliente:</label>
    <select name="cliente_id" id="cliente_id" required>
        <option value="">-- Seleccionar --</option>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>">
                <?= $c['apellido'] . ', ' . $c['nombre'] . ' (' . $c['dni'] . ')' ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Seleccionar Plan:</label>
    <select name="plan_id" id="plan_id" onchange="cargarDatosPlan(this.value)" required>
        <option value="">-- Seleccionar plan --</option>
        <?php while ($p = $planes->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Precio del Plan:</label>
    <input type="number" name="precio" id="precio" readonly>

    <label>Clases Disponibles:</label>
    <input type="number" name="clases_disponibles" id="clases_disponibles" readonly>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly>

    <input type="hidden" name="duracion_meses" id="duracion_meses">

    <label>Otros Pagos:</label>
    <input type="number" name="otros_pagos" id="otros_pagos" value="0" oninput="calcularTotal()">

    <label>Forma de Pago:</label>
    <select name="forma_pago" id="forma_pago" onchange="calcularTotal()" required>
        <option value="efectivo">Efectivo</option>
        <option value="transferencia">Transferencia</option>
        <option value="debito">Débito</option>
        <option value="credito">Crédito</option>
        <option value="cuenta_corriente">Cuenta Corriente</option>
    </select>

    <label>Total a Pagar:</label>
    <input type="number" name="total" id="total" readonly>

    <button type="submit">Registrar Membresía</button>
    <a href="index.php"><button type="button">Volver al Menú</button></a>

</form>

<script>
document.getElementById('fecha_inicio').valueAsDate = new Date();

function filtrarClientes() {
    let filtro = document.getElementById('buscador').value.toLowerCase();
    let select = document.getElementById('cliente_id');

    for (let i = 0; i < select.options.length; i++) {
        let texto = select.options[i].text.toLowerCase();
        select.options[i].style.display = texto.includes(filtro) ? '' : 'none';
    }
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
            calcularTotal();
        });
}

function calcularVencimiento() {
    const inicio = document.getElementById('fecha_inicio').value;
    const meses = parseInt(document.getElementById('duracion_meses').value || 0);

    if (inicio && meses > 0) {
        let fecha = new Date(inicio);
        fecha.setMonth(fecha.getMonth() + meses);
        document.getElementById('fecha_vencimiento').value = fecha.toISOString().split('T')[0];
    }
}

function calcularTotal() {
    let precio = parseFloat(document.getElementById('precio').value || 0);
    let otros = parseFloat(document.getElementById('otros_pagos').value || 0);
    let forma = document.getElementById('forma_pago').value;

    let total = precio + otros;
    if (forma === 'cuenta_corriente') {
        total = -Math.abs(total);
    }

    document.getElementById('total').value = total;
}
</script>

</body>
</html>
