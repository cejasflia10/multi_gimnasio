<?php include 'menu.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Agregar Cliente</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #111;
      color: gold;
      margin: 0;
      padding: 20px;
    }

    .form-container {
      max-width: 600px;
      margin: auto;
      background-color: #222;
      padding: 20px;
      border-radius: 10px;
    }

    h2 {
      text-align: center;
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin: 10px 0 5px;
    }

    input, select {
      width: 100%;
      padding: 8px;
      background-color: #333;
      border: 1px solid #555;
      color: white;
      border-radius: 5px;
    }

    button {
      margin-top: 20px;
      padding: 10px;
      width: 100%;
      background-color: gold;
      border: none;
      color: black;
      font-weight: bold;
      cursor: pointer;
      border-radius: 5px;
    }

    button:hover {
      background-color: #e6b800;
    }
  </style>

  <script>
    function calcularEdad() {
      const nacimiento = document.getElementById('fecha_nacimiento').value;
      if (nacimiento) {
        const hoy = new Date();
        const cumple = new Date(nacimiento);
        let edad = hoy.getFullYear() - cumple.getFullYear();
        const m = hoy.getMonth() - cumple.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < cumple.getDate())) {
          edad--;
        }
        document.getElementById('edad').value = edad;
      }
    }
  </script>
</head>
<body>
  <div class="form-container">
    <h2>Agregar Cliente</h2>
    <form action="guardar_cliente.php" method="POST">
      <label for="apellido">Apellido *</label>
      <input type="text" name="apellido" id="apellido" required>

      <label for="nombre">Nombre *</label>
      <input type="text" name="nombre" id="nombre" required>

      <label for="dni">DNI *</label>
      <input type="text" name="dni" id="dni" required>

      <label for="fecha_nacimiento">Fecha de nacimiento *</label>
      <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required onchange="calcularEdad()">

      <label for="edad">Edad *</label>
      <input type="number" name="edad" id="edad" readonly required>

      <label for="domicilio">Domicilio *</label>
      <input type="text" name="domicilio" id="domicilio" required>

      <label for="telefono">Teléfono</label>
      <input type="text" name="telefono" id="telefono">

      <label for="email">Email</label>
      <input type="email" name="email" id="email">

      <label for="rfid">RFID (opcional)</label>
      <input type="text" name="rfid" id="rfid">

      <label for="gimnasio">Gimnasio *</label>
      <input type="text" name="gimnasio" id="gimnasio" required>

      <button type="submit">Guardar Cliente</button>
    </form>
  </div>
</body>
</html>
