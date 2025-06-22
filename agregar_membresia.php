<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "conexion.php";

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

$planes = $conexion->query("SELECT id, nombre, precio, clases FROM planes WHERE gimnasio_id = $gimnasio_id");
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nueva Membresía</title>
  <style>
    body { background-color: #000; color: gold; font-family: Arial; padding: 10px; }
    input, select { width: 100%; padding: 8px; margin-bottom: 10px; border: 2px solid gold; background-color: #222; color: white; }
    .btn { background: gold; color: black; padding: 10px; border: none; cursor: pointer; width: 100%; }
    .container { max-width: 500px; margin: auto; }
  </style>
</head>
<body>
<div class="container">
  <h2>Registrar Nueva Membresía</h2>
  <form action="guardar_membresia.php" method="POST">
    <label>Buscar Cliente (DNI):</label>
    <input type="text" id="buscar_cliente" placeholder="Escriba DNI, nombre o RFID" autocomplete="off">
    <select name="cliente_id" id="cliente_id" required>
      <option value="">Seleccione un cliente</option>
    </select>

    <label>Seleccionar Plan:</label>
    <select name="plan_id" id="plan_id" required>
      <option value="">Seleccione un plan</option>
      <?php while($p = $planes->fetch_assoc()): ?>
        <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>" data-clases="<?= $p['clases'] ?>">
          <?= $p['nombre'] ?> - $<?= number_format($p['precio'], 2) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= date('Y-m-d') ?>">

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly>

    <label>Días disponibles (clases):</label>
    <input type="number" name="clases_disponibles" id="clases_disponibles" readonly>

    <label>Planes Adicionales:</label>
    <select name="adicional_id" id="adicional_id">
      <option value="">Ninguno</option>
      <?php while($a = $adicionales->fetch_assoc()): ?>
        <option value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>"><?= $a['nombre'] ?> - $<?= number_format($a['precio'],2) ?></option>
      <?php endwhile; ?>
    </select>

    <label>Otros Pagos:</label>
    <input type="number" name="otros_pagos" id="otros_pagos" value="0">

    <label>Método de Pago:</label>
    <select name="metodo_pago" required>
      <option value="Efectivo">Efectivo</option>
      <option value="Transferencia">Transferencia</option>
      <option value="Cuenta Corriente">Cuenta Corriente</option>
    </select>

    <label>Total a Pagar:</label>
    <input type="number" name="total_pagar" id="total" readonly>

    <button class="btn">Guardar</button>
    <a href="index.php" class="btn" style="margin-top:10px; background:#333; color:gold;">Volver al Panel</a>
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

  // Cargar clases disponibles
  const clases = plan?.dataset.clases || 0;
  document.getElementById("clases_disponibles").value = clases;

  // Calcular vencimiento = fecha inicio + 1 mes
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
</script>

</body>
</html>
