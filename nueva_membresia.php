<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
$clientes = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
$adicionales = $conexion->query("SELECT id, nombre FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
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
        input, select, button {
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
            cursor: pointer;
        }
        .logo {
            text-align: center;
            padding: 15px;
        }
        .logo img {
            width: 130px;
        }
        .descuentos button {
            width: 24%;
            margin: 2px;
            background-color: #444;
            color: gold;
            font-size: 14px;
        }
        .descuentos button:hover {
            background-color: gold;
            color: black;
        }

        @media screen and (max-width: 600px) {
            .container {
                padding: 10px;
            }
            input, select, button {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>

<div class="logo">
    <img src="logo.png" alt="Logo del gimnasio">
</div>

<div class="container">
<h1>Agregar Membresía</h1>

<form action="guardar_membresia.php" method="POST">
    <label for="buscador">Buscar Cliente (DNI o Apellido):</label>
    <input type="text" id="buscador" placeholder="Buscar..." onkeyup="filtrarClientes()">

    <label for="cliente_id">Seleccionar Cliente:</label>
    <select name="cliente_id" id="cliente_id" required>
        <option value="">-- Seleccionar --</option>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>">
                <?= $c['apellido'] . ', ' . $c['nombre'] . ' (' . $c['dni'] . ')' ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label for="plan_id">Seleccionar Plan:</label>
    <select name="plan_id" id="plan_id" onchange="cargarDatosPlan(this.value)" required>
        <option value="">-- Seleccionar plan --</option>
        <?php mysqli_data_seek($planes, 0); while ($p = $planes->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Precio del Plan:</label>
    <input type="number" name="precio" id="precio" readonly>

    <div class="descuentos">
        <label>Aplicar Descuento:</label>
        <button type="button" onclick="aplicarDescuento(10)">-10%</button>
        <button type="button" onclick="aplicarDescuento(15)">-15%</button>
        <button type="button" onclick="aplicarDescuento(25)">-25%</button>
        <button type="button" onclick="aplicarDescuento(50)">-50%</button>
    </div>

    <label>Clases Disponibles:</label>
    <input type="number" name="clases_disponibles" id="clases_disponibles" readonly>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" onchange="calcularVencimiento()" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento">

    <input type="hidden" id="duracion_meses" name="duracion_meses">

    <label>Pagos Adicionales:</label>
    <input type="number" id="pagos_adicionales" name="pagos_adicionales" value="0" oninput="calcularTotal()">

    <label>Otros Pagos:</label>
    <input type="number" id="otros_pagos" name="otros_pagos" value="0" oninput="calcularTotal()">

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
</div>

<script>
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
    let adicionales = parseFloat(document.getElementById('pagos_adicionales').value || 0);
    let otros = parseFloat(document.getElementById('otros_pagos').value || 0);
    let forma = document.getElementById('forma_pago').value;
    let total = precio + adicionales + otros;

    if (forma === 'cuenta_corriente') {
        total = -Math.abs(total);
    }

    document.getElementById('total').value = total.toFixed(2);
}

function aplicarDescuento(porcentaje) {
    let original = parseFloat(document.getElementById('precio').value || 0);
    let descuento = original * (porcentaje / 100);
    document.getElementById('precio').value = (original - descuento).toFixed(2);
    calcularTotal();
}

function filtrarClientes() {
    let input = document.getElementById('buscador').value.toLowerCase();
    let opciones = document.getElementById('cliente_id').options;

    for (let i = 0; i < opciones.length; i++) {
        let texto = opciones[i].text.toLowerCase();
        opciones[i].style.display = texto.includes(input) ? '' : 'none';
    }
}

// Fecha de hoy por defecto
document.getElementById('fecha_inicio').valueAsDate = new Date();
</script>

</body>
</html>
