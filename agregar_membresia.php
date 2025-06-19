<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener planes
$planes_result = $conexion->query("SELECT id, nombre, precio, cantidad_clases, duracion_meses FROM planes WHERE gimnasio_id = $gimnasio_id");

// Obtener planes adicionales
$adicionales_result = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Nueva Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            margin: auto;
        }
        h2 {
            text-align: center;
            color: gold;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 8px;
            border-radius: 5px;
            margin-top: 5px;
            border: 1px solid gold;
            background-color: #333;
            color: white;
        }
        button {
            background-color: gold;
            color: black;
            padding: 10px;
            margin-top: 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
        }
        button:hover {
            background-color: #ffd700;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Registrar Nueva Membresía</h2>
        <form action="guardar_membresia.php" method="POST">
            <label for="buscar">Buscar Cliente (DNI):</label>
            <input type="text" name="buscar" id="buscar" placeholder="Escriba DNI">

            <label for="plan">Seleccionar Plan:</label>
            <select name="plan" id="plan">
                <?php while ($row = $planes_result->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>">
                        <?= $row['nombre'] ?> - $<?= number_format($row['precio'], 2) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="fecha_inicio">Fecha de Inicio:</label>
            <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>">

            <label for="plan_adicional">Planes Adicionales:</label>
            <select name="plan_adicional" id="plan_adicional">
                <option value="0">Ninguno</option>
                <?php while ($row = $adicionales_result->fetch_assoc()): ?>
                    <option value="<?= $row['precio'] ?>"><?= $row['nombre'] ?> - $<?= number_format($row['precio'], 2) ?></option>
                <?php endwhile; ?>
            </select>

            <label for="otros_pagos">Otros Pagos:</label>
            <input type="number" step="0.01" name="otros_pagos" value="0">

            <label for="metodo_pago">Método de Pago:</label>
            <select name="metodo_pago" id="metodo_pago">
                <option value="Efectivo">Efectivo</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Tarjeta Débito">Tarjeta Débito</option>
                <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                <option value="Cuenta Corriente">Cuenta Corriente</option>
            </select>

            <label for="total">Total a Pagar:</label>
            <input type="text" name="total" id="total" value="">

            <button type="submit">Registrar Membresía</button>
        </form>
        <a href="index.php"><button>Volver al Panel</button></a>
    </div>
</body>
</html>
