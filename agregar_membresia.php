<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;

if (!$gimnasio_id) {
    die("Acceso denegado.");
}

// Obtener planes
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");

// Obtener planes adicionales
$adicionales = $conexion->query("SELECT * FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");

// Obtener clientes
$clientes = $conexion->query("SELECT * FROM clientes WHERE gimnasio_id = $gimnasio_id");
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
            color: #ffd700;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        form {
            max-width: 600px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            margin-bottom: 12px;
            border: 1px solid #ffd700;
            background-color: #333;
            color: #ffd700;
            border-radius: 4px;
        }
        input[type="submit"] {
            background-color: #ffd700;
            color: #111;
            font-weight: bold;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #e5c100;
        }
    </style>
</head>
<body>
    <h2>Agregar Nueva Membresía</h2>
    <form action="guardar_membresia.php" method="POST">
        <label>Seleccionar Cliente:</label>
        <select name="cliente_id" required>
            <option value="">Seleccione...</option>
            <?php while($c = $clientes->fetch_assoc()) { ?>
                <option value="<?php echo $c['id']; ?>">
                    <?php echo $c['apellido'] . " " . $c['nombre'] . " - DNI: " . $c['dni']; ?>
                </option>
            <?php } ?>
        </select>

        <label>Plan:</label>
        <select name="plan_id" required>
            <option value="">Seleccione...</option>
            <?php while($p = $planes->fetch_assoc()) { ?>
                <option value="<?php echo $p['id']; ?>">
                    <?php echo $p['nombre'] . " - $" . $p['precio']; ?>
                </option>
            <?php } ?>
        </select>

        <label>Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" required>

        <label>Fecha de Vencimiento:</label>
        <input type="date" name="fecha_vencimiento" required>

        <label>Clases Disponibles:</label>
        <input type="number" name="clases_disponibles" required>

        <label>Planes Adicionales:</label>
        <select name="adicional_id">
            <option value="">Ninguno</option>
            <?php while($a = $adicionales->fetch_assoc()) { ?>
                <option value="<?php echo $a['id']; ?>">
                    <?php echo $a['nombre'] . " - $" . $a['precio']; ?>
                </option>
            <?php } ?>
        </select>

        <label>Otros Pagos:</label>
        <input type="number" name="otros_pagos" min="0" step="0.01" placeholder="0">

        <label>Método de Pago:</label>
        <select name="metodo_pago" required>
            <option value="Efectivo">Efectivo</option>
            <option value="Transferencia">Transferencia</option>
            <option value="Tarjeta Débito">Tarjeta Débito</option>
            <option value="Tarjeta Crédito">Tarjeta Crédito</option>
            <option value="Cuenta Corriente">Cuenta Corriente</option>
        </select>

        <label>Total a Pagar:</label>
        <input type="number" name="total_pagar" step="0.01" required>

        <input type="submit" value="Registrar Membresía">
    </form>
</body>
</html>
