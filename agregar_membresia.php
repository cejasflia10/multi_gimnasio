<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["gimnasio_id"])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';
?>
<!-- Resto del formulario aquí -->
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Membresía</title>
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .container {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            max-width: 600px;
            margin: auto;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            background-color: #333;
            color: #fff;
            border: 1px solid #FFD700;
            border-radius: 5px;
        }
        button {
            background-color: #FFD700;
            color: #111;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Nueva Membresía</h2>
    <form method="POST" action="guardar_membresia.php">
        <label>Buscar Cliente (DNI, Nombre, RFID):</label>
        <input type="text" name="busqueda_cliente" placeholder="Escriba DNI, nombre o RFID" required>

        <label>Seleccionar Plan:</label>
        <select name="plan_id" required>
            <option value="">Seleccione un plan</option>
            <!-- Cargar desde la BD -->
        </select>

        <!-- Más campos pueden agregarse aquí como fecha, adicionales, métodos de pago, etc. -->

        <button type="submit">Guardar Membresía</button>
    </form>
</div>
</body>
</html>
