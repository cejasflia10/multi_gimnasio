<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro de Cliente</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    .formulario {
      max-width: 400px;
      margin: auto;
      background-color: #222;
      padding: 20px;
      border-radius: 8px;
    }
    input, button {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      border: none;
      border-radius: 4px;
    }
    button {
      background-color: gold;
      font-weight: bold;
      color: #000;
      cursor: pointer;
    }
    label {
      margin-top: 10px;
      display: block;
    }
  </style>
</head>
<body>
  <div class="formulario">
    <h2>Registro Online</h2>
    <form id="formRegistro">
      <label>Apellido:</label>
      <input type="text" name="apellido" required>

      <label>Nombre:</label>
      <input type="text" name="nombre" required>

      <label>DNI:</label>
      <input type="text" name="dni" required>

      <label>Fecha de nacimiento:</label>
      <input type="date" name="fecha_nacimiento" required>

      <label>Domicilio:</label>
      <input type="text" name="domicilio" required>

      <label>Teléfono:</label>
      <input type="text" name="telefono" required>

      <label>Email:</label>
      <input type="email" name="email" required>

      <label>RFID (opcional):</label>
      <input type="text" name="rfid_uid">

      <button type="submit">Registrar</button>
    </form>
    <div id="respuesta" style="margin-top: 10px;"></div>
  </div>

  <script>
    document.getElementById("formRegistro").addEventListener("submit", function(e) {
      e.preventDefault();
      const form = e.target;
      const datos = new FormData(form);

      fetch("registrar_cliente_online.php", {
        method: "POST",
        body: datos
      })
      .then(res => res.json())
      .then(data => {
        document.getElementById("respuesta").innerText = data.message;
        if (data.success) form.reset();
      })
      .catch(err => {
        document.getElementById("respuesta").innerText = "Error al registrar.";
      });
    });
  </script>
</body>
</html>
