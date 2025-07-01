<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$id = $_GET['id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$membresia = $conexion->query("SELECT * FROM membresias WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
$clientes = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        form {
            max-width: 500px;
            margin: auto;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input, select, button {
            width: 100%;
            padding: 10px;
            background-color: #111;
            color: gold;
            border: 1px solid gold;
            border-radius: 5px;
            margin-top: 5px;
        }
        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            margin-top: 20px;
        }
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
        }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>
<h1>Editar Membresía</h1>
<form action="guardar_edicion_membresia.php" method="POST">
    <input type="hidden" name="id" value="<?= $membresia['id'] ?>">

    <label>Cliente:</label>
    <select name="cliente_id" required>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $membresia['cliente_id'] ? 'selected' : '' ?>>
                <?= $c['apellido'] . ', ' . $c['nombre'] . ' (' . $c['dni'] . ')' ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Plan:</label>
    <select name="plan_id" id="plan_id" required>
        <?php
        $planesData = [];
        while ($p = $planes->fetch_assoc()):
            $selected = $p['id'] == $membresia['plan_id'] ? 'selected' : '';
            echo "<option value='{$p['id']}' $selected>{$p['nombre']}</option>";
            $planesData[$p['id']] = [
                'precio' => $p['precio'],
                'clases' => $p['clases_disponibles'],
                'duracion_meses' => $p['duracion_meses']
            ];
        endwhile;
        ?>
    </select>

    <label>Precio:</label>
    <input type="number" step="0.01" name="precio" id="precio" value="<?= $membresia['precio'] ?>" required>

    <label>Clases Disponibles:</label>
    <input type="number" name="clases_disponibles" id="clases_disponibles" value="<?= $membresia['clases_restantes'] ?>" required>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= $membresia['fecha_inicio'] ?>" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="<?= $membresia['fecha_vencimiento'] ?>" required>

    <label>Otros Pagos:</label>
    <input type="number" step="0.01" name="otros_pagos" value="<?= $membresia['otros_pagos'] ?>">

    <label>Forma de Pago:</label>
    <select name="forma_pago" required>
        <option value="efectivo" <?= $membresia['forma_pago'] == 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
        <option value="transferencia" <?= $membresia['forma_pago'] == 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
        <option value="debito" <?= $membresia['forma_pago'] == 'debito' ? 'selected' : '' ?>>Débito</option>
        <option value="credito" <?= $membresia['forma_pago'] == 'credito' ? 'selected' : '' ?>>Crédito</option>
        <option value="cuenta_corriente" <?= $membresia['forma_pago'] == 'cuenta_corriente' ? 'selected' : '' ?>>Cuenta Corriente</option>
    </select>

    <label>Total:</label>
    <input type="number" step="0.01" name="total" id="total" value="<?= $membresia['total'] ?>" required>

    <button type="submit">Guardar Cambios</button>
    <a href="ver_membresias.php"><button type="button">Volver</button></a>
</form>

<script>
const planes = <?= json_encode($planesData) ?>;
const selectPlan = document.getElementById('plan_id');
const precio = document.getElementById('precio');
const clases = document.getElementById('clases_disponibles');
const fechaInicio = document.getElementById('fecha_inicio');
const fechaVencimiento = document.getElementById('fecha_vencimiento');

function actualizarCampos() {
    const id = selectPlan.value;
    const datos = planes[id];
    if (datos) {
        precio.value = datos.precio;
        clases.value = datos.clases;

        const inicio = new Date(fechaInicio.value);
        if (!isNaN(inicio)) {
            inicio.setMonth(inicio.getMonth() + parseInt(datos.duracion_meses));
            const vencimiento = inicio.toISOString().split('T')[0];
            fechaVencimiento.value = vencimiento;
        }
    }
}

selectPlan.addEventListener('change', actualizarCampos);
</script>
</body>
</html>
