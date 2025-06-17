<?php
include 'conexion.php';

// Obtener disciplinas
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas ORDER BY nombre");

// Obtener profesores
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores ORDER BY apellido");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar Nueva Membresía</title>
  <style>
    body {
      background-color: #111;
      color: #ffc107;
      font-family: Arial, sans-serif;
      padding: 30px;
    }
    label, select, input {
      display: block;
      margin: 10px 0;
    }
    input, select {
      padding: 8px;
      width: 100%;
      max-width: 400px;
      background: #222;
      color: #ffc107;
      border: 1px solid #ffc107;
    }
    .cliente-info {
      margin-top: 10px;
      padding: 10px;
      border: 1px dashed #ffc107;
    }
  </style>
  <script>
    async function buscarCliente(valor) {
      if (valor.length < 2) return;
      const res = await fetch('obtener_cliente_id.php?q=' + encodeURIComponent(valor));
      const data = await res.json();
      if (data && data.id) {
        document.getElementById('cliente_info').innerHTML =
          `<strong>Cliente:</strong> ${data.apellido}, ${data.nombre}<br>
           <strong>DNI:</strong> ${data.dni}<br>
           <strong>Disciplina:</strong> ${data.disciplina ?? 'No asignada'}`;
        document.getElementById('cliente_id').value = data.id;
      } else {
        document.getElementById('cliente_info').innerHTML = 'Cliente no encontrado';
        document.getElementById('cliente_id').value = '';
      }
    }
  </script>
</head>
<body>
  <h2>Registrar Nueva Membresía</h2>

  <form action="guardar_membresia.php" method="POST">
    <label for="buscar">Buscar cliente (DNI / Apellido / RFID):</label>
    <input type="text" id="buscar" onkeyup="buscarCliente(this.value)" autocomplete="off">
    <div id="cliente_info" class="cliente-info"></div>
    <input type="hidden" name="cliente_id" id="cliente_id">

    <label for="disciplina">Asignar disciplina (si no está cargada):</label>
    <select name="disciplina_id" id="disciplina">
      <option value="">-- Seleccionar disciplina --</option>
      <?php while ($row = $disciplinas->fetch_assoc()): ?>
        <option value="<?= $row['id'] ?>"><?= $row['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label for="profesor">Asignar profesor (opcional):</label>
    <select name="profesor_id" id="profesor">
      <option value="">-- Sin profesor --</option>
      <?php while ($row = $profesores->fetch_assoc()): ?>
        <option value="<?= $row['id'] ?>"><?= $row['apellido'] ?>, <?= $row['nombre'] ?></option>
      <?php endwhile; ?>
    </select>

    <label for="fecha_inicio">Fecha de inicio:</label>
    <input type="date" name="fecha_inicio" required>

    <button type="submit">Registrar Membresía</button>
  </form>
</body>
</html>
