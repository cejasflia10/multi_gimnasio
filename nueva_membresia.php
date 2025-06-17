<?php
include 'conexion.php';
$planes = $conexion->query("SELECT id, nombre, precio FROM planes");
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales");
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas");
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nueva Membresía</title>
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px 280px;
    }
    h2 {
      text-align: center;
    }
    label {
      display: block;
      margin-top: 12px;
      font-weight: bold;
    }
    input, select {
      width: 100%;
      padding: 8px;
      margin-top: 4px;
      background: #222;
      border: 1px solid gold;
      color: white;
    }
    #resultado_cliente {
      background: #222;
      border: 1px solid gold;
      padding: 10px;
      margin-top: 8px;
      font-size: 14px;
    }
    #total {
      margin-top: 10px;
      font-weight: bold;
    }
    button {
      margin-top: 20px;
      background: gold;
      color: black;
      padding: 10px 20px;
      border: none;
      font-weight: bold;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <h2>Registrar Nueva Membresía</h2>
  <form action="guardar_membresia.php" method="POST">
    <label for="dni_buscar">Buscar Cliente por DNI / Apellido / RFID:</label>
    <input type="text" name="dni_buscar" id="dni_buscar" placeholder="Ej: 24533 o Cejas">
    <div id="resultado_cliente">Cliente no encontrado</div>
    <input type="hidden" name="cliente_id" id="cliente_id">

    <label for="plan_id">Plan de Membresía:</label>
    <select name="plan_id" id="plan_id" required onchange="calcularTotal()">
      <option value="">-- Seleccionar plan --</option>
      <?php while($p = $planes->fetch_assoc()): ?>
        <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>"><?= $p['nombre'] ?> - $<?= $p['precio'] ?></option>
      <?php endwhile; ?>
    </select>

    <label for="adicional_id">Adicional (opcional):</label>
    <select name="adicional_id" id="adicional_id" onchange="calcularTotal()">
      <option value="">-- Ninguno --</option>
      <?php while($a = $adicionales->fetch_assoc()): ?>
        <option value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>"><?= $a['nombre'] ?> - $<?= $a['precio'] ?></option>
      <?php endwhile; ?>
    </select>

    <label for="disciplina_id">Disciplina:</label>
    <select name="disciplina_id" id="disciplina_id" required>
      <option value="">-- Seleccionar disciplina --</option>
      <?php while($d = $disciplinas->fetch_assoc()): ?>
        <option value="<?= $d['id'] ?>"><?= $d['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label for="profesor_id">Asignar Profesor (opcional):</label>
    <select name="profesor_id" id="profesor_id">
      <option value="">-- Sin profesor --</option>
      <?php while($pr = $profesores->fetch_assoc()): ?>
        <option value="<?= $pr['id'] ?>"><?= $pr['apellido'] ?> <?= $pr['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label for="fecha_inicio">Fecha de inicio:</label>
    <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>">

    <label for="pago1">Método de Pago 1:</label>
    <select name="pago1">
      <option value="efectivo">Efectivo</option>
      <option value="transferencia">Transferencia</option>
      <option value="tarjeta">Tarjeta</option>
      <option value="otro">Otro</option>
    </select>

    <label for="monto1">Monto $:</label>
    <input type="number" name="monto1" step="0.01" placeholder="Ej: 5000">

    <label for="pago2">Método de Pago 2 (opcional):</label>
    <select name="pago2">
      <option value="">-- Ninguno --</option>
      <option value="efectivo">Efectivo</option>
      <option value="transferencia">Transferencia</option>
      <option value="tarjeta">Tarjeta</option>
      <option value="otro">Otro</option>
    </select>

    <label for="monto2">Monto $:</label>
    <input type="number" name="monto2" step="0.01" placeholder="Ej: 5000">

    <div id="total">Total: $0</div>

    <button type="submit">Guardar Membresía</button>
  </form>

  <script>
    function calcularTotal() {
      const plan = document.querySelector('#plan_id option:checked').dataset.precio || 0;
      const adicional = document.querySelector('#adicional_id option:checked').dataset.precio || 0;
      const total = parseFloat(plan) + parseFloat(adicional);
      document.getElementById('total').textContent = 'Total: $' + total;
    }

    document.getElementById("dni_buscar").addEventListener("input", function() {
      const valor = this.value;
      if (valor.length >= 2) {
        fetch("buscar_cliente.php?filtro=" + valor)
          .then(r => r.json())
          .then(data => {
            if (data.length > 0) {
              const cliente = data[0];
              document.getElementById("cliente_id").value = cliente.id;
              document.getElementById("resultado_cliente").innerHTML = 
                `<strong>Cliente:</strong> ${cliente.apellido}, ${cliente.nombre}<br>
                 <strong>DNI:</strong> ${cliente.dni}<br>
                 <strong>Disciplina:</strong> ${cliente.disciplina || 'No asignada'}`;
            } else {
              document.getElementById("cliente_id").value = "";
              document.getElementById("resultado_cliente").innerHTML = "Cliente no encontrado";
            }
          });
      } else {
        document.getElementById("cliente_id").value = "";
        document.getElementById("resultado_cliente").innerHTML = "Cliente no encontrado";
      }
    });
  </script>
</body>
</html>
