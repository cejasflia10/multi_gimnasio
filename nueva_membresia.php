<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Obtener planes
$planes = $conexion->query("SELECT id, nombre, precio FROM planes ORDER BY nombre ASC");

// Obtener adicionales
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nueva Membresía</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h2 {
      color: #ffc107;
      text-align: center;
    }
    .formulario {
      max-width: 700px;
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
      padding: 10px;
      margin-top: 5px;
      background-color: #333;
      color: white;
      border: none;
      border-radius: 5px;
    }
    .resultado {
      background-color: #333;
      color: #ffc107;
      padding: 5px;
      margin-top: 5px;
      cursor: pointer;
    }
    .total {
      margin-top: 15px;
      text-align: right;
      font-size: 18px;
      color: #ffc107;
    }
    .btn {
      background-color: #ffc107;
      color: #111;
      padding: 12px;
      margin-top: 20px;
      width: 100%;
      font-size: 16px;
      cursor: pointer;
      border: none;
      border-radius: 5px;
    }
    .advertencia {
      background-color: #ff4444;
      color: white;
      padding: 10px;
      margin-top: 10px;
      text-align: center;
      font-weight: bold;
      display: none;
    }
  </style>
</head>
<body>

<h2>Registrar Nueva Membresía</h2>
<div class="formulario">
  <form method="post" action="guardar_membresia.php">
    <label for="buscar">Buscar cliente (DNI / RFID / Apellido)</label>
    <input type="text" id="buscar" autocomplete="off" placeholder="Escriba DNI, apellido o RFID...">
    <div id="resultados"></div>

    <input type="hidden" name="cliente_id" id="cliente_id" required>

    <div id="advertencia" class="advertencia"></div>

    <label>Plan</label>
    <select name="plan_id" id="plan" onchange="calcularTotal()" required>
      <option value="">-- Seleccionar plan --</option>
      <?php while ($p = $planes->fetch_assoc()): ?>
        <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>">
          <?= $p['nombre'] ?> - $<?= $p['precio'] ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label>Adicionales</label>
    <select name="adicional_id" id="adicional" onchange="calcularTotal()">
      <option value="0" data-precio="0">-- Ninguno --</option>
      <?php while ($a = $adicionales->fetch_assoc()): ?>
        <option value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>">
          <?= $a['nombre'] ?> - $<?= $a['precio'] ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label>Fecha de inicio</label>
    <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>

    <label>Método de pago</label>
    <select name="metodo_pago" required>
      <option value="Efectivo">Efectivo</option>
      <option value="Transferencia">Transferencia</option>
      <option value="Débito">Tarjeta Débito</option>
      <option value="Crédito">Tarjeta Crédito</option>
      <option value="Cuenta Corriente">Cuenta Corriente</option>
    </select>

    <div class="total">Total: $<span id="total">0</span></div>

    <button type="submit" class="btn">Registrar Membresía</button>
  </form>
</div>

<script>
function calcularTotal() {
  const plan = document.querySelector("#plan option:checked");
  const adicional = document.querySelector("#adicional option:checked");

  const precioPlan = parseFloat(plan.dataset.precio || 0);
  const precioAdicional = parseFloat(adicional.dataset.precio || 0);

  document.getElementById("total").innerText = (precioPlan + precioAdicional).toFixed(2);
}

// Buscar cliente automáticamente
const inputBuscar = document.getElementById('buscar');
const resultados = document.getElementById('resultados');
const advertencia = document.getElementById('advertencia');

inputBuscar.addEventListener('keyup', function () {
  const valor = this.value.trim();
  resultados.innerHTML = '';
  advertencia.style.display = 'none';

  if (valor.length > 2) {
    fetch('buscar_cliente.php?q=' + valor)
      .then(res => res.json())
      .then(data => {
        data.forEach(cliente => {
          const div = document.createElement('div');
          div.classList.add('resultado');
          div.innerText = `${cliente.apellido}, ${cliente.nombre} - DNI: ${cliente.dni}`;
          div.onclick = () => {
            document.getElementById('buscar').value = `${cliente.apellido}, ${cliente.nombre}`;
            document.getElementById('cliente_id').value = cliente.id;
            resultados.innerHTML = '';
            // Verificar membresía
            fetch(`verificar_membresia.php?id=${cliente.id}`)
              .then(resp => resp.json())
              .then(info => {
                if (!info.valida) {
                  advertencia.innerText = 'Este cliente no tiene una membresía activa o ya está vencida';
                  advertencia.style.display = 'block';
                }
              });
          };
          resultados.appendChild(div);
        });
      });
  }
});
</script>

</body>
</html>
