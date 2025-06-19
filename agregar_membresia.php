<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Obtener los planes
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");

// Obtener planes adicionales
$adicionales = $conexion->query("SELECT * FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Registrar Nueva Membresía</title>
  <style>
    body {
      background-color: #000;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    input, select, button {
      width: 100%;
      padding: 10px;
      margin: 5px 0 15px;
      border: 1px solid gold;
      background: #222;
      color: white;
    }
    label {
      font-weight: bold;
      display: block;
    }
    .container {
      max-width: 600px;
      margin: auto;
    }
    .btn-volver {
      background: darkred;
      color: white;
      border: none;
      text-align: center;
      padding: 10px;
      text-decoration: none;
      display: inline-block;
      margin-top: 20px;
    }
  </style>
  <script>
    function calcularTotal() {
        var plan = parseFloat(document.getElementById("plan").selectedOptions[0].dataset.precio || 0);
        var adicional = parseFloat(document.getElementById("adicional").selectedOptions[0].dataset.precio || 0);
        var otros = parseFloat(document.getElementById("otros_pagos").value || 0);
        var total = plan + adicional + otros;
        document.getElementById("total").value = total.toFixed(2);
    }

    function calcularVencimiento() {
        const fechaInicio = new Date(document.getElementById("inicio").value);
        const meses = parseInt(document.getElementById("plan").selectedOptions[0].dataset.meses || 1);
        fechaInicio.setMonth(fechaInicio.getMonth() + meses);
        document.getElementById("vencimiento").value = fechaInicio.toISOString().split('T')[0];
    }
  </script>
</head>
<body>
<div class="container">
  <h2>Registrar Nueva Membresía</h2>
  <form action="guardar_membresia.php" method="POST">
    <label>Buscar Cliente (DNI):</label>
    <input type="text" name="dni" placeholder="Ingrese DNI" required />

    <label>Seleccionar Plan:</label>
    <select name="plan" id="plan" onchange="calcularTotal(); calcularVencimiento();" required>
      <option value="">Seleccione un plan</option>
      <?php while ($p = $planes->fetch_assoc()): ?>
      <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>" data-meses="<?= $p['duracion_meses'] ?>">
        <?= $p['nombre'] ?> - $<?= number_format($p['precio'], 2) ?>
      </option>
      <?php endwhile; ?>
    </select>

    <label>Fecha de Inicio:</label>
    <input type="date" name="inicio" id="inicio" value="<?= date('Y-m-d') ?>" onchange="calcularVencimiento();" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="vencimiento" id="vencimiento" readonly>

    <label>Planes Adicionales:</label>
    <select name="adicional" id="adicional" onchange="calcularTotal();">
      <option value="">Ninguno</option>
      <?php while ($a = $adicionales->fetch_assoc()): ?>
      <option value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>">
        <?= $a['nombre'] ?> - $<?= number_format($a['precio'], 2) ?>
      </option>
      <?php endwhile; ?>
    </select>

    <label>Otros Pagos:</label>
    <input type="number" name="otros_pagos" id="otros_pagos" oninput="calcularTotal();" placeholder="0">

    <label>Método de Pago:</label>
    <select name="metodo_pago" required>
      <option value="Efectivo">Efectivo</option>
      <option value="Transferencia">Transferencia</option>
      <option value="Cuenta Corriente">Cuenta Corriente</option>
    </select>

    <label>Total a Pagar:</label>
    <input type="text" name="total" id="total" readonly>

    <button type="submit">Registrar Membresía</button>
  </form>
  <a href="index.php" class="btn-volver">← Volver al Panel</a>
</div>
</body>
</html>