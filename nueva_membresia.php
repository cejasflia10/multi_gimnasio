<?php
include 'conexion.php';
include 'menu.php';

// Obtener planes y adicionales
$planes = $conexion->query("SELECT id, nombre, precio FROM planes");
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales");
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas ORDER BY nombre");
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores ORDER BY apellido");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nueva Membresía</title>
  <style>
    body {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  min-height: 100vh;
  margin: 0;

      background-color: #111;
      color: #ffc107;
      font-family: Arial, sans-serif;
      margin-left: 220px;
      padding: 20px;
    }
    h2 {
      color: #ffc107;
      margin-bottom: 20px;
    }
    label {
      display: block;
      margin-top: 10px;
      font-weight: bold;
    }
    input, select {
      width: 100%%;
      max-width: 400px;
      padding: 8px;
      margin-top: 5px;
      background-color: #222;
      color: #ffc107;
      border: 1px solid #ffc107;
    }
    .resumen-cliente {
      margin-top: 10px;
      padding: 10px;
      border: 1px dashed #ffc107;
    }
    button {
      margin-top: 20px;
      background-color: #ffc107;
      color: #111;
      border: none;
      padding: 10px 20px;
      cursor: pointer;
      font-weight: bold;
    }
  </style>
  <script>
    async function buscarCliente(valor) {
      if (valor.length < 2) return;
      const res = await fetch('obtener_cliente_id.php?q=' + encodeURIComponent(valor));
      const data = await res.json();
      if (data && data.id) {
        document.getElementById('resumen_cliente').innerHTML =
          `<strong>Cliente:</strong> ${data.apellido}, ${data.nombre}<br>
           <strong>DNI:</strong> ${data.dni}<br>
           <strong>Disciplina:</strong> ${data.disciplina ?? 'No asignada'}`;
        document.getElementById('cliente_id').value = data.id;
      } else {
        document.getElementById('resumen_cliente').innerHTML = 'Cliente no encontrado';
        document.getElementById('cliente_id').value = '';
      }
    }

    function actualizarTotal() {
      const plan = document.querySelector('#plan');
      const adicional = document.querySelector('#adicional');
      let total = 0;
      if (plan && plan.selectedOptions[0]) total += parseFloat(plan.selectedOptions[0].dataset.precio || 0);
      if (adicional && adicional.selectedOptions[0]) total += parseFloat(adicional.selectedOptions[0].dataset.precio || 0);
      document.getElementById('total').textContent = '$' + total;
    }
  </script>
  <script>
async function buscarCliente(valor) {
  if (valor.length < 2) {
    document.getElementById('resumen_cliente').innerHTML = '';
    return;
  }

  const res = await fetch('buscar_cliente.php?q=' + encodeURIComponent(valor));
  const datos = await res.json();

  const contenedor = document.getElementById('resumen_cliente');
  contenedor.innerHTML = '';

  if (!datos.length) {
    contenedor.innerHTML = '<span style="color: #ffc107;">Cliente no encontrado</span>';
    document.getElementById('cliente_id').value = '';
    return;
  }

  const lista = document.createElement('ul');
  lista.style.listStyle = 'none';
  lista.style.padding = 0;

  datos.forEach(cliente => {
    const item = document.createElement('li');
    item.style.cursor = 'pointer';
    item.style.marginBottom = '5px';
    item.style.padding = '5px';
    item.style.border = '1px dashed #ffc107';
    item.textContent = `${cliente.apellido}, ${cliente.nombre} - DNI: ${cliente.dni}`;
    item.onclick = () => {
      contenedor.innerHTML = `<strong>Cliente:</strong> ${cliente.apellido}, ${cliente.nombre}<br>
                               <strong>DNI:</strong> ${cliente.dni}<br>
                               <strong>RFID:</strong> ${cliente.rfid ?? 'No registrado'}`;
      document.getElementById('cliente_id').value = cliente.id;
    };
    lista.appendChild(item);
  });

  contenedor.appendChild(lista);
}
  </script>
</head>
<body>
  <div style="width: 100%; max-width: 800px;">
  <h2>Registrar Nueva Membresía</h2>

  <form action="guardar_membresia.php" method="POST">
    <label>Buscar cliente (DNI / Apellido / RFID):</label>
    <input type="text" onkeyup="buscarCliente(this.value)">
    <div id="resumen_cliente" class="resumen-cliente"></div>
    <input type="hidden" name="cliente_id" id="cliente_id">

    <label>Asignar disciplina (si no está cargada):</label>
    <select name="disciplina_id">
      <option value="">-- Seleccionar disciplina --</option>
      <?php while ($row = $disciplinas->fetch_assoc()): ?>
        <option value="<?= $row['id'] ?>"><?= $row['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label>Asignar profesor (opcional):</label>
    <select name="profesor_id">
      <option value="">-- Sin profesor --</option>
      <?php while ($row = $profesores->fetch_assoc()): ?>
        <option value="<?= $row['id'] ?>"><?= $row['apellido'] ?>, <?= $row['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label>Plan:</label>
    <select name="plan_id" id="plan" onchange="actualizarTotal()">
      <option value="">-- Seleccionar plan --</option>
      <?php while ($row = $planes->fetch_assoc()): ?>
        <option value="<?= $row['id'] ?>" data-precio="<?= $row['precio'] ?>"><?= $row['nombre'] ?> ($<?= $row['precio'] ?>)</option>
      <?php endwhile; ?>
    </select>

    <label>Adicionales:</label>
    <select name="adicional_id" id="adicional" onchange="actualizarTotal()">
      <option value="">-- Ninguno --</option>
      <?php while ($row = $adicionales->fetch_assoc()): ?>
        <option value="<?= $row['id'] ?>" data-precio="<?= $row['precio'] ?>"><?= $row['nombre'] ?> ($<?= $row['precio'] ?>)</option>
      <?php endwhile; ?>
    </select>

    <label>Fecha de inicio:</label>
    <input type="date" name="fecha_inicio" required>

    <label>Método de pago:</label>
    <select name="metodo_pago" required>
      <option value="Efectivo">Efectivo</option>
      <option value="Transferencia">Transferencia</option>
      <option value="Débito">Débito</option>
      <option value="Crédito">Crédito</option>
      <option value="Cuenta Corriente">Cuenta Corriente</option>
    </select>

    <p><strong>Total:</strong> <span id="total">$0</span></p>

    <button type="submit">Registrar Membresía</button>
  </form>
  </div>
</body>
</html>
