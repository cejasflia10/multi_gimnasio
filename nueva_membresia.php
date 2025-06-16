<?php
include 'conexion.php';
include 'menu.php';
$planes = mysqli_query($conexion, "SELECT * FROM planes");
$adicionales = mysqli_query($conexion, "SELECT * FROM planes_adicionales");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar Nueva Membresía</title>
  <style>
    body { background-color: #111; color: #f1c40f; font-family: Arial, sans-serif; margin: 0; }
    .contenido { margin-left: 240px; padding: 20px; max-width: 700px; }
    label { font-weight: bold; margin-top: 10px; display: block; }
    input, select {
      width: 100%; padding: 10px; margin-top: 5px;
      background-color: #222; color: #fff; border: 1px solid #f1c40f; border-radius: 4px;
    }
    #resultado_cliente {
      margin-top: 10px;
      padding: 10px;
      background: #1c1c1c;
      border: 1px solid #f1c40f;
      display: none;
    }
    button {
      margin-top: 20px;
      background: #f1c40f;
      color: #111;
      border: none;
      padding: 10px 20px;
      font-weight: bold;
      border-radius: 5px;
      cursor: pointer;
    }
    button:hover { background-color: #d4ac0d; }
  </style>
  <script>
    function buscarCliente() {
      const valor = document.getElementById('buscar_cliente').value;
      if (valor.length < 3) return;

      fetch('buscar_cliente_ajax.php?query=' + valor)
        .then(response => response.json())
        .then(data => {
          if (data && data.id) {
            document.getElementById('cliente_id').value = data.id;
            document.getElementById('disciplina_id').value = data.disciplina_id;
            document.getElementById('resultado_cliente').style.display = 'block';
            document.getElementById('resultado_cliente').innerHTML =
              '<strong>Cliente:</strong> ' + data.apellido + ', ' + data.nombre + '<br>' +
              '<strong>DNI:</strong> ' + data.dni + '<br>' +
              '<strong>Disciplina:</strong> ' + data.disciplina;
          } else {
            document.getElementById('resultado_cliente').innerHTML = 'No encontrado';
            document.getElementById('resultado_cliente').style.display = 'block';
          }
        });
    }
  </script>
</head>
<body>
<div class="contenido">
  <h2>Registrar Nueva Membresía</h2>
  <form action="guardar_membresia.php" method="POST">
    <label>Buscar cliente (DNI / RFID / Apellido)</label>
    <input type="text" id="buscar_cliente" onkeyup="buscarCliente()" placeholder="Escriba DNI, apellido o RFID...">

    <div id="resultado_cliente"></div>

    <input type="hidden" name="cliente_id" id="cliente_id">
    <input type="hidden" name="disciplina_id" id="disciplina_id">

    <label>Plan:</label>
    <select name="plan_id" required>
      <option value="">-- Seleccionar plan --</option>
      <?php while ($plan = mysqli_fetch_assoc($planes)) { ?>
        <option value="<?php echo $plan['id']; ?>"><?php echo $plan['nombre'] . ' - $' . $plan['precio']; ?></option>
      <?php } ?>
    </select>

    <label>Adicionales:</label>
    <select name="adicional_id">
      <option value="">-- Ninguno --</option>
      <?php while ($a = mysqli_fetch_assoc($adicionales)) { ?>
        <option value="<?php echo $a['id']; ?>"><?php echo $a['nombre'] . ' - $' . $a['precio']; ?></option>
      <?php } ?>
    </select>

    <label>Fecha de inicio:</label>
    <input type="date" name="fecha_inicio" required>

    <label>Método de pago:</label>
    <select name="metodo_pago" required>
      <option value="efectivo">Efectivo</option>
      <option value="transferencia">Transferencia</option>
      <option value="tarjeta_debito">Tarjeta Débito</option>
      <option value="tarjeta_credito">Tarjeta Crédito</option>
      <option value="cuenta_corriente">Cuenta Corriente</option>
    </select>

    <button type="submit">Guardar Membresía</button>
  </form>
</div>
</body>
</html>
