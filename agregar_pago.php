<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$clientes = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Pago</title>
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
        }
        label {
            margin-top: 12px;
            display: block;
        }
        input, select, button {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: none;
            margin-top: 5px;
        }
        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<h1>Registrar Pago</h1>

<form action="guardar_pago.php" method="POST">
    <label for="buscador">Buscar Cliente (DNI o Apellido):</label>
    <input type="text" id="buscador" placeholder="Buscar..." onkeyup="filtrarClientes()">

    <label>Seleccionar Cliente:</label>
    <select name="cliente_id" id="cliente_id" onchange="cargarMembresias(this.value)" required>
        <option value="">-- Seleccionar cliente --</option>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>"><?= $c['apellido'] . ', ' . $c['nombre'] . ' (' . $c['dni'] . ')' ?></option>
        <?php endwhile; ?>
    </select>

    <label>Seleccionar Membresía Activa:</label>
    <select name="membresia_id" id="membresia_id" required>
        <option value="">-- Esperando selección de cliente --</option>
    </select>

    <label>Fecha del Pago:</label>
    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>

    <label>Monto:</label>
    <input type="number" name="monto" step="0.01" required>

    <label>Forma de Pago:</label>
    <select name="forma_pago" required>
        <option value="efectivo">Efectivo</option>
        <option value="transferencia">Transferencia</option>
        <option value="debito">Débito</option>
        <option value="credito">Crédito</option>
        <option value="cuenta_corriente">Cuenta Corriente</option>
    </select>

    <button type="submit">Registrar Pago</button>
    <a href="ver_pagos.php"><button type="button">Volver</button></a>
</form>

<script>
function filtrarClientes() {
    let input = document.getElementById('buscador').value.toLowerCase();
    let opciones = document.getElementById('cliente_id').options;

    for (let i = 0; i < opciones.length; i++) {
        let texto = opciones[i].text.toLowerCase();
        opciones[i].style.display = texto.includes(input) ? '' : 'none';
    }
}

function cargarMembresias(clienteId) {
    const select = document.getElementById('membresia_id');
    select.innerHTML = '<option value="">Cargando...</option>';

    fetch('obtener_membresias_cliente.php?cliente_id=' + clienteId)
        .then(res => res.json())
        .then(data => {
            select.innerHTML = '';
            if (data.length === 0) {
                select.innerHTML = '<option value="">No tiene membresías activas</option>';
            } else {
                select.innerHTML = '<option value="">-- Seleccionar --</option>';
                data.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.text = m.plan + ' (' + m.fecha_inicio + ' al ' + m.fecha_vencimiento + ')';
                    select.appendChild(opt);
                });
            }
        });
}
</script>

</body>
</html>
