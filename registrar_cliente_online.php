<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro Online - Fight Academy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background-color: #000;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    .container {
      max-width: 500px;
      margin: auto;
      background-color: #111;
      padding: 20px;
      border-radius: 10px;
    }
    input, select {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      border: none;
      border-radius: 5px;
    }
    button {
      background-color: gold;
      color: black;
      padding: 10px;
      border: none;
      margin-top: 20px;
      width: 100%;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Registro de Cliente</h2>
    <form id="formRegistro">
      <input type="text" name="apellido" placeholder="Apellido" required>
      <input type="text" name="nombre" placeholder="Nombre" required>
      <input type="text" name="dni" placeholder="DNI" required>
      <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required>
      <input type="number" name="edad" id="edad" placeholder="Edad" readonly>
      <input type="text" name="domicilio" placeholder="Domicilio" required>
      <input type="text" name="telefono" placeholder="Teléfono" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="text" name="rfid" placeholder="RFID (opcional)">
      <button type="submit">Registrar</button>
    </form>
    <div id="respuesta"></div>
  </div>

  <script>
    document.getElementById('fecha_nacimiento').addEventListener('change', function () {
      const fecha = new Date(this.value);
      const hoy = new Date();
      let edad = hoy.getFullYear() - fecha.getFullYear();
      const m = hoy.getMonth() - fecha.getMonth();
      if (m < 0 || (m === 0 && hoy.getDate() < fecha.getDate())) {
        edad--;
      }
      document.getElementById('edad').value = edad;
    });

    document.getElementById('formRegistro').addEventListener('submit', function (e) {
      e.preventDefault();
      const datos = new FormData(this);
      fetch('registrar_cliente_online.php', {
        method: 'POST',
        body: datos
      })
      .then(res => res.json())
      .then(data => {
        document.getElementById('respuesta').innerText = data.message;
      })
      .catch(() => {
        document.getElementById('respuesta').innerText = "Error de conexión.";
      });
    });
  </script>
</body>
</html>
