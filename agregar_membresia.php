
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "conexion.php";

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

$planes = $conexion->query("SELECT id, nombre, precio FROM planes WHERE gimnasio_id = $gimnasio_id");
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
    <select name="cliente_id" id="cliente_id" required></select>

    <label>Seleccionar Plan:</label>
    <select name="plan_id" id="plan_id" required>
      <option value="">Seleccione un plan</option>
      <?php while($p = $planes->fetch_assoc()): ?>
        <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?> - $<?= number_format($p['precio'], 2) ?></option>
      <?php endwhile; ?>
    </select>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= date('Y-m-d') ?>">

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento">

    <label>Clases Disponibles:</label>
    <input type="number" name="clases" id="clases" readonly>

    <label>Planes Adicionales:</label>
    <select name="adicional_id" id="adicional_id">
      <option value="">Ninguno</option>
      <?php while($a = $adicionales->fetch_assoc()): ?>
        <option value="<?= $a['id'] ?>"><?= $a['nombre'] ?> - $<?= number_format($a['precio'],2) ?></option>
      <?php endwhile; ?>
    </select>

    <label>Otros Pagos:</label>
    <input type="number" name="otros_pagos" id="otros_pagos" value="0">

    <label>Método de Pago:</label>
    <select name="metodo_pago">
      <option value="Efectivo">Efectivo</option>
      <option value="Transferencia">Transferencia</option>
      <option value="Cuenta Corriente">Cuenta Corriente</option>
    </select>

    <label>Total a Pagar:</label>
    <input type="number" name="total" id="total" readonly>

    <button class="btn">Guardar</button>
    <a href="index.php" class="btn" style="margin-top:10px; background:#333; color:gold;">Volver al Panel</a>
  </form>
</div>

<script src="script.js"></script>
</body>
</html>
