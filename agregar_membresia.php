<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION["gimnasio_id"] ?? 0;

// Obtener planes
$planes = [];
$result = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
while ($row = $result->fetch_assoc()) {
    $planes[] = $row;
}

// Obtener planes adicionales
$adicionales = [];
$result2 = $conexion->query("SELECT * FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
while ($row2 = $result2->fetch_assoc()) {
    $adicionales[] = $row2;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .form-container {
            max-width: 600px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin: 6px 0 12px;
            background-color: #333;
            color: #fff;
            border: 1px solid #FFD700;
            border-radius: 5px;
        }
        label {
            font-weight: bold;
        }
        .btn {
            background-color: #FFD700;
            color: black;
            font-weight: bold;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
    <script>
        function calcularTotal() {
            const planSelect = document.getElementById("plan");
            const adicionalSelect = document.getElementById("adicional");
            const otrosPagos = parseFloat(document.getElementById("otros_pagos").value) || 0;
            const metodo = planSelect.options[planSelect.selectedIndex].dataset.precio || 0;
            const adicional = adicionalSelect.options[adicionalSelect.selectedIndex].dataset.precio || 0;

            const total = parseFloat(metodo) + parseFloat(adicional) + otrosPagos;
            document.getElementById("total").value = total.toFixed(2);
        }
    </script>
</head>
<body>
    <div class="form-container">
        <h2>Registrar Nueva Membresía</h2>
        <form action="guardar_membresia.php" method="POST">
            <label for="dni">Buscar Cliente (DNI):</label>
            <input type="text" name="dni" required>

            <label for="plan">Seleccionar Plan:</label>
            <select name="plan_id" id="plan" onchange="calcularTotal()" required>
                <option value="">Seleccione un plan</option>
                <?php foreach ($planes as $p): ?>
                    <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>">
                        <?= $p['nombre'] ?> - $<?= number_format($p['precio'], 2) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="fecha_inicio">Fecha de Inicio:</label>
            <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>

            <label for="adicional">Planes Adicionales:</label>
            <select name="adicional_id" id="adicional" onchange="calcularTotal()">
                <option value="">Ninguno</option>
                <?php foreach ($adicionales as $a): ?>
                    <option value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>">
                        <?= $a['nombre'] ?> - $<?= number_format($a['precio'], 2) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="otros_pagos">Otros Pagos:</label>
            <input type="number" step="0.01" id="otros_pagos" name="otros_pagos" oninput="calcularTotal()">

            <label for="metodo">Método de Pago:</label>
            <select name="metodo_pago" required>
                <option value="efectivo">Efectivo</option>
                <option value="transferencia">Transferencia</option>
                <option value="cuenta_corriente">Cuenta Corriente</option>
                <option value="tarjeta">Tarjeta</option>
            </select>

            <label for="total">Total a Pagar:</label>
            <input type="text" id="total" name="total" readonly>

            <button type="submit" class="btn">Registrar Membresía</button>
        </form>
    </div>
</body>
</html>
