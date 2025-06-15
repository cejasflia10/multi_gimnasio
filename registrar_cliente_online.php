<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro Online - Fight Academy Scorpions</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      padding: 20px;
    }

    h2 {
      color: gold;
      text-align: center;
    }

    form {
      max-width: 500px;
      margin: auto;
      background-color: #222;
      padding: 20px;
      border-radius: 10px;
    }

    label {
      display: block;
      margin-top: 10px;
      font-weight: bold;
    }

    input, select {
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      border-radius: 5px;
      border: none;
    }

    input[type="submit"] {
      background-color: gold;
      color: #111;
      font-weight: bold;
      margin-top: 20px;
      cursor: pointer;
    }

    .mensaje {
      text-align: center;
      margin-top: 10px;
      color: lightgreen;
    }

    .error {
      color: red;
    }
  </style>
</head>
<body>
  <h2>Registro Online</h2>

  <?php if (isset($_SESSION['mensaje'])): ?>
    <div class="mensaje"><?= $_SESSION['mensaje'] ?></div>
    <?php unset($_SESSION['mensaje']); ?>
  <?php endif; ?>

  <form action="registro_online_guardar.php" method="POST">
    <label for="apellido">Apellido:</label>
    <input type="text" name="apellido" required>

    <label for="nombre">Nombre:</label>
    <input type="text" name="nombre" required>

    <label for="dni">DNI:</label>
    <input type="text" name="dni" required>

    <label for="fecha_nacimiento">Fecha de nacimiento:</label>
    <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" onchange="calcularEdad()" required>

    <label for="edad">Edad:</label>
    <input type="number" name="edad" id="edad" readonly required>

    <label for="domicilio">Domicilio:</label>
    <input type="text" name="domicilio" required>

    <label for="email">Email:</label>
    <input type="email" name="email">

    <label for="telefono">Teléfono:</label>
    <input type="text" name="telefono">

    <label for="rfid">RFID:</label>
    <input type="text" name="rfid" required>

    <input type="submit" value="Registrarse">
  </form>

  <script>
    function calcularEdad() {
      const nacimiento = document.getElementById("fecha_nacimiento").value;
      if (nacimiento) {
        const hoy = new Date();
        const fechaNacimiento = new Date(nacimiento);
        let edad = hoy.getFullYear() - fechaNacimiento.getFullYear();
        const mes = hoy.getMonth() - fechaNacimiento.getMonth();

        if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNacimiento.getDate())) {
          edad--;
        }

        document.getElementById("edad").value = edad;
      }
    }
  </script>
</body>
</html>
