<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['id'])) {
    die("ID de membresía no proporcionado.");
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$membresia = $conexion->query("SELECT * FROM membresias WHERE id = $id AND gimnasio_id = $gimnasio_id LIMIT 1");

if (!$membresia || $membresia->num_rows === 0) {
    die("Membresía no encontrada.");
}

$membresia = $membresia->fetch_assoc();
$cliente_id = intval($membresia['cliente_id']);
$cliente = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE id = $cliente_id LIMIT 1");

if (!$cliente || $cliente->num_rows === 0) {
    die("Cliente no encontrado.");
}

$cliente = $cliente->fetch_assoc();
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Renovar Membresía</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { background-color: #000; color: gold; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
    .container { max-width: 600px; margin: auto; padding: 20px; }
    label { display: block; margin-top: 10px; font-weight: bold; }
    input, select, button {
      width: 100%; padding: 10px; margin-top: 5px;
      background-color: #111; color: gold; border: 1px solid gold; border-radius: 6px;
    }
    h1 { text-align: center; margin-bottom: 20px; }
    button {
      background-color: gold; color: black; font-weight: bold; cursor: pointer; margin-top: 20px;
    }
    .descuentos button {
      width: 23%; margin: 5px 1%; background-color: #444;
    }
    .descuentos { text-align: center; margin-top: 10px; }
    @media screen and (max-width: 600px) {
      .container { padding: 10px; }
      input, select, button { font-size: 15px; }
      .descuentos button { width: 45%; margin: 5px 2%; }
    }
  </style>
</head>
<body>
<div class="container">
<h1>Renovar Membresía</h1>
<form action="guardar_renovacion.php" method="POST">
  <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">

  <label>Cliente:</label>
  <input type="text" value="<?= $cliente['apellido'] . ', ' . $cliente['nombre'] . ' (' . $cliente['dni'] . ')' ?>" readonly>

  <label>Nuevo Plan:</label>
  <select name="plan_id" id="plan_id" onchange="cargarDatosPlan(this.value)" required>
    <option value="">-- Seleccionar --</option>
    <?php while ($p = $planes->fetch_assoc()): ?>
      <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
    <?php endwhile; ?>
  </select>

  <label>Precio:</label>
  <input type="number" name="precio" id="precio" required>

  <div class="descuentos">
    <label>Aplicar Descuento:</label>
    <button type="button" onclick="aplicarDescuento(10)">-10%</button>
    <button type="button" onclick="aplicarDescuento(15)">-15%</button>
    <button type="button" onclick="aplicarDescuento(25)">-25%</button>
    <button type="button" onclick="aplicarDescuento(50)">-50%</button>
  </div>

  <label>Clases Disponibles:</label>
  <input type="number" name="clases_disponibles" id="clases_disponibles" required>

  <label>Fecha de Inicio:</label>
  <input type="date" name="fecha_inicio" id="fecha_inicio" required>

  <label>Fecha de Vencimiento:</label>
  <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required>

  <label>Otros Pagos:</label>
  <input type="number" name="otros_pagos" id="otros_pagos" value="0" oninput="calcularTotal()">

  <label>Forma de Pago:</label>
  <select name="forma_pago" id="forma_pago" onchange="calcularTotal()" required>
    <option value="efectivo">Efectivo</option>
    <option value="transferencia">Transferencia</option>
    <option value="debito">Débito</option>
    <option value="credito">Crédito</option>
    <option value="cuenta_corriente">Cuenta Corriente</option>
  </select>

  <label>Total:</label>
  <input type="number" name="total" id="total" required readonly>

  <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">
  <input type="hidden" name="duracion_meses" id="duracion_meses">

  <button type="submit">Confirmar Renovación</button>
  <a href="ver_membresias.php"><button type="button">Cancelar</button></a>
</form>
</div>

<script>
function cargarDatosPlan(planId) {
  fetch('obtener_datos_plan.php?plan_id=' + planId)
    .then(res => res.json())
    .then(data => {
      document.getElementById('precio').value = data.precio;
      document.getElementById('clases_disponibles').value = data.clases_disponibles;
      document.getElementById('duracion_meses').value = data.duracion_meses;

      const inicio = document.getElementById('fecha_inicio').value;
      if (inicio && data.duracion_meses > 0) {
        const fecha = new Date(inicio);
        fecha.setMonth(fecha.getMonth() + parseInt(data.duracion_meses));
        document.getElementById('fecha_vencimiento').value = fecha.toISOString().split('T')[0];
      }

      calcularTotal();
    });
}

function calcularTotal() {
  let precio = parseFloat(document.getElementById('precio').value || 0);
  let otros = parseFloat(document.getElementById('otros_pagos').value || 0);
  document.getElementById('total').value = (precio + otros).toFixed(2);
}

function aplicarDescuento(porcentaje) {
  let precio = parseFloat(document.getElementById('precio').value || 0);
  let descuento = precio * (porcentaje / 100);
  document.getElementById('precio').value = (precio - descuento).toFixed(2);
  calcularTotal();
}

document.getElementById('fecha_inicio').valueAsDate = new Date();
document.getElementById('fecha_inicio').addEventListener('change', () => {
  const meses = parseInt(document.getElementById('duracion_meses').value || 0);
  const inicio = document.getElementById('fecha_inicio').value;
  if (inicio && meses > 0) {
    const fecha = new Date(inicio);
    fecha.setMonth(fecha.getMonth() + meses);
    document.getElementById('fecha_vencimiento').value = fecha.toISOString().split('T')[0];
  }
});
</script>

</body>
</html>