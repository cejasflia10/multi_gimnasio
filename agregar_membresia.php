
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["gimnasio_id"])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';

// Cargar planes desde BD
$planes = $conexion->query("SELECT id, nombre, precio, clases FROM planes WHERE gimnasio_id = $gimnasio_id");
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
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
    <select name="plan_id" id="plan_id" onchange="actualizarTotal()" required>
      <option value="" data-precio="0" data-clases="0">Seleccione un plan</option>
      <?php while($row = $planes->fetch_assoc()): ?>
        <option value="<?php echo $row['id']; ?>" data-precio="<?php echo $row['precio']; ?>" data-clases="<?php echo $row['clases']; ?>">
          <?php echo $row['nombre'] . ' - $' . $row['precio']; ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label for="fecha_inicio">Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" value="<?php echo date('Y-m-d'); ?>" required />

    <label for="fecha_vencimiento">Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" required />

    <label for="clases_disponibles">Clases Disponibles:</label>
    <input type="number" name="clases_disponibles" id="clases_disponibles" readonly />

    <label for="plan_adicional">Planes Adicionales:</label>
    <select name="plan_adicional" id="plan_adicional" onchange="actualizarTotal()">
      <option value="" data-precio="0">Ninguno</option>
      <?php while($row = $adicionales->fetch_assoc()): ?>
        <option value="<?php echo $row['id']; ?>" data-precio="<?php echo $row['precio']; ?>">
          <?php echo $row['nombre'] . ' - $' . $row['precio']; ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label for="otros_pagos">Otros Pagos:</label>
    <input type="number" name="otros_pagos" id="otros_pagos" value="0" step="0.01" oninput="actualizarTotal()" />

    <label for="forma_pago">Forma de Pago:</label>
    <select name="forma_pago" required>
      <option value="efectivo">Efectivo</option>
      <option value="transferencia">Transferencia</option>
      <option value="tarjeta">Tarjeta</option>
      <option value="cuenta_corriente">Cuenta Corriente</option>
    </select>

    <label for="total">Total a Pagar:</label>
    <input type="number" name="total" id="total" step="0.01" readonly />

    <div class="button-group">
      <button type="submit">Guardar</button>
      <a href="index.php"><button type="button">Volver al Panel</button></a>
    </div>
  </form>
</div>

<script>
function actualizarTotal() {
    const plan = document.getElementById('plan_id');
    const adicional = document.getElementById('plan_adicional');
    const otros = parseFloat(document.getElementById('otros_pagos').value) || 0;

    const planPrecio = parseFloat(plan.options[plan.selectedIndex].getAttribute('data-precio')) || 0;
    const clases = parseInt(plan.options[plan.selectedIndex].getAttribute('data-clases')) || 0;
    const adicionalPrecio = parseFloat(adicional.options[adicional.selectedIndex].getAttribute('data-precio')) || 0;

    const total = planPrecio + adicionalPrecio + otros;

    document.getElementById('total').value = total.toFixed(2);
    document.getElementById('clases_disponibles').value = clases;
}
</script>
</body>
</html>
