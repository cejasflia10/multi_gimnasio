<?php
include 'conexion.php';
include 'menu.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
            background-color: #1c1c1c;
            border-radius: 10px;
        }
        h2 {
            text-align: center;
        }
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background-color: #222;
            border: 1px solid #f1c40f;
            color: #fff;
            border-radius: 5px;
        }
        button {
            background-color: #f1c40f;
            color: #000;
            padding: 10px;
            width: 100%;
            margin-top: 20px;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #d4ac0d;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Registrar Nueva Membresía</h2>
    <form action="guardar_membresia.php" method="POST">
        <label>Buscar cliente:</label>
        <input type="text" id="buscar_cliente" placeholder="Escriba nombre, apellido, DNI o RFID">

        <label>Seleccionar cliente:</label>
        <select name="cliente_id" id="cliente_id" required>
            <option value="">Seleccione un cliente</option>
        </select>

        <label>Fecha de inicio:</label>
        <input type="date" name="fecha_inicio" id="fecha_inicio" required>

        <label>Seleccionar plan:</label>
        <select name="plan_id" id="plan_id" required>
            <option value="">Seleccione un plan</option>
            <?php
            $planes = mysqli_query($conexion, "SELECT * FROM planes");
            while ($row = mysqli_fetch_assoc($planes)) {
                echo "<option value='{$row['id']}' data-precio='{$row['precio']}' data-clases='{$row['clases']}'>{$row['nombre']} - \${$row['precio']}</option>";
            }
            ?>
        </select>

        <label>Adicional:</label>
        <select name="adicional_id" id="adicional_id">
            <option value="">Ninguno</option>
            <?php
            $adicionales = mysqli_query($conexion, "SELECT * FROM planes_adicionales");
            while ($row = mysqli_fetch_assoc($adicionales)) {
                echo "<option value='{$row['id']}' data-precio='{$row['precio']}'>{$row['nombre']} - \${$row['precio']}</option>";
            }
            ?>
        </select>

        <label>Otros pagos:</label>
        <input type="number" step="0.01" name="otros_pagos" id="otros_pagos" placeholder="0.00">

        <label>Método de pago:</label>
        <select name="metodo_pago" required>
            <option value="">Seleccione</option>
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="tarjeta débito">Tarjeta Débito</option>
            <option value="tarjeta crédito">Tarjeta Crédito</option>
            <option value="cuenta corriente">Cuenta Corriente</option>
        </select>

        <label>Total a pagar:</label>
        <input type="text" name="total" id="total" readonly>

        <label>Clases disponibles:</label>
        <input type="number" id="clases_disponibles" readonly placeholder="Se carga según el plan">

        <label>Fecha de vencimiento:</label>
        <input type="date" id="fecha_vencimiento" readonly>

        <button type="submit">Registrar Membresía</button>
    </form>
</div>

<script>
// Buscar cliente automáticamente
document.getElementById("buscar_cliente").addEventListener("input", function () {
    const valor = this.value;
    const clienteSelect = document.getElementById("cliente_id");

    if (valor.length >= 2) {
        fetch("buscar_cliente.php?q=" + valor)
            .then(res => res.json())
            .then(data => {
                clienteSelect.innerHTML = "<option value=''>Seleccione un cliente</option>";
                data.forEach(cliente => {
                    const opt = document.createElement("option");
                    opt.value = cliente.id;
                    opt.textContent = cliente.text;
                    clienteSelect.appendChild(opt);
                });
            });
    }
});

// Calcular total, clases y vencimiento
function actualizarTotal() {
    const plan = document.querySelector("#plan_id option:checked");
    const adicional = document.querySelector("#adicional_id option:checked");
    const otrosPagos = parseFloat(document.getElementById("otros_pagos").value || 0);

    const precioPlan = parseFloat(plan?.dataset.precio || 0);
    const precioAdicional = parseFloat(adicional?.dataset.precio || 0);
    const total = precioPlan + precioAdicional + otrosPagos;
    document.getElementById("total").value = total.toFixed(2);

    const clases = plan?.dataset.clases || 0;
    document.getElementById("clases_disponibles").value = clases;

    const inicio = document.getElementById("fecha_inicio").value;
    if (inicio) {
        const fecha = new Date(inicio);
        fecha.setMonth(fecha.getMonth() + 1);
        const vencimiento = fecha.toISOString().split('T')[0];
        document.getElementById("fecha_vencimiento").value = vencimiento;
    }
}

document.getElementById("plan_id").addEventListener("change", actualizarTotal);
document.getElementById("adicional_id").addEventListener("change", actualizarTotal);
document.getElementById("otros_pagos").addEventListener("input", actualizarTotal);
document.getElementById("fecha_inicio").addEventListener("change", actualizarTotal);
window.addEventListener("load", actualizarTotal);
</script>
</body>
</html>
