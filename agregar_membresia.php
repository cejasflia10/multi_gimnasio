
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

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Agregar Membresía</title>
  <style>
    body {
      background-color: #111;
      color: #FFD700;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 800px;
      margin: auto;
      background-color: #222;
      padding: 20px;
      border-radius: 10px;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    label {
      font-weight: bold;
    }
    input, select {
      width: 100%;
      padding: 10px;
      margin: 8px 0 16px;
      border-radius: 5px;
      border: 1px solid #FFD700;
      background-color: #333;
      color: #fff;
    }
    button {
      background-color: #FFD700;
      color: #111;
      padding: 10px 20px;
      font-weight: bold;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    .button-group {
      display: flex;
      justify-content: space-between;
    }
  </style>
</head>
<body>
<div class="container">
  <h2>Registrar Nueva Membresía</h2>
  <form action="guardar_membresia.php" method="POST">
    <label for="cliente_id">Buscar Cliente:</label>
    <input type="text" name="cliente_busqueda" placeholder="Buscar por nombre, DNI o RFID" required />

    <label for="plan_id">Seleccionar Plan:</label>
    <select name="plan_id" required>
      <option value="">Seleccione un plan</option>
      <!-- Opciones desde BD -->
    </select>

    <label for="fecha_inicio">Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" value="<?php echo date('Y-m-d'); ?>" required />

    <label for="fecha_vencimiento">Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" required />

    <label for="clases_disponibles">Clases Disponibles:</label>
    <input type="number" name="clases_disponibles" required />

    <label for="plan_adicional">Planes Adicionales:</label>
    <select name="plan_adicional">
      <option value="">Ninguno</option>
      <!-- Opciones desde BD -->
    </select>

    <label for="otros_pagos">Otros Pagos (opcional):</label>
    <input type="number" name="otros_pagos" step="0.01" value="0">

    <label for="forma_pago">Forma de Pago:</label>
    <select name="forma_pago" required>
      <option value="efectivo">Efectivo</option>
      <option value="transferencia">Transferencia</option>
      <option value="tarjeta">Tarjeta</option>
      <option value="cuenta_corriente">Cuenta Corriente</option>
    </select>

    <label for="total">Total a Pagar:</label>
    <input type="number" name="total" step="0.01" required />

    <div class="button-group">
      <button type="submit">Guardar</button>
      <a href="index.php"><button type="button">Volver al Panel</button></a>
    </div>
  </form>
</div>
</body>
</html>
