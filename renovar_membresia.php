<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID de membresía no especificado.");
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$membresia = $conexion->query("SELECT * FROM membresias WHERE id = $id AND id_gimnasio = $gimnasio_id")->fetch_assoc();
$cliente = $conexion->query("SELECT * FROM clientes WHERE id = {$membresia['cliente_id']}")->fetch_assoc();
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Renovar Membresía</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
    .container { max-width: 600px; margin: auto; background: #222; padding: 20px; border-radius: 10px; }
    label { display: block; margin-top: 15px; font-weight: bold; }
    input, select, button { width: 100%; padding: 10px; margin-top: 5px; border-radius: 6px; border: none; }
    button { background: gold; color: black; font-weight: bold; margin-top: 20px; cursor: pointer; }
  </style>
</head>
<body>
<div class="container">
  <h2>Renovar Membresía de <?= $cliente['apellido'] . ', ' . $cliente['nombre'] ?> </h2>

  <form method="POST" action="guardar_renovacion.php">
    <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">

    <label>Plan:</label>
    <select name="plan_id" id="plan_id" onchange="cargarDatosPlan(this.value)" required>
      <option value="">-- Seleccionar plan --</option>
      <?php mysqli_data_seek($planes, 0); while ($p = $planes->fetch_assoc()): ?>
        <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label>Precio:</label>
    <input type="number" id="precio" name="precio" readonly>

    <label>Clases Disponibles:</label>
    <input type="number" id="clases_disponibles" name="clases_disponibles" readonly>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required>

    <label>Pagos Adicionales:</label>
    <input type="number" id="pagos_adicionales" name="pagos_adicionales" value="0">

    <label>Otros Pagos:</label>
    <input type="number" id="otros_pagos" name="otros_pagos" value="0">

    <label>Forma de Pago:</label>
    <select name="forma_pago" id="forma_pago" required>
      <option value="efectivo">Efectivo</option>
      <option value="transferencia">Transferencia</option>
      <option value="debito">Débito</option>
      <option value="credito">Crédito</option>
      <option value="cuenta_corriente">Cuenta Corriente</option>
    </select>

    <label>Total a Pagar:</label>
    <input type="number" name="total" id="total" readonly>

    <button type="submit">Confirmar Renovación</button>
  </form>
</div>

<script>
function cargarDatosPlan(planId) {
  if (!planId) return;

  fetch('obtener_datos_plan.php?plan_id=' + planId)
    .then(res => res.json())
    .then(data => {
      document.getElementById('precio').value = data.precio;
      document.getElementById('clases_disponibles').value = data.clases_disponibles;
      let inicio = document.getElementById('fecha_inicio').valueAsDate = new Date();
      let fechaVto = new Date();
      fechaVto.setMonth(fechaVto.getMonth() + parseInt(data.duracion_meses || 1));
      document.getElementById('fecha_vencimiento').value = fechaVto.toISOString().split('T')[0];
      calcularTotal();
    });
}

function calcularTotal() {
  let precio = parseFloat(document.getElementById('precio').value || 0);
  let adicionales = parseFloat(document.getElementById('pagos_adicionales').value || 0);
  let otros = parseFloat(document.getElementById('otros_pagos').value || 0);
  let total = precio + adicionales + otros;

  let forma = document.getElementById('forma_pago').value;
  if (forma === 'cuenta_corriente') total = -Math.abs(total);

  document.getElementById('total').value = total;
}
</script>

</body>
</html>
