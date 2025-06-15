<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Registro Online - Fight Academy</title>
  <style>
    body {
      background-color: #111;
      color: #fff;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: start;
      min-height: 100vh;
    }

    .container {
      width: 100%;
      max-width: 450px;
      padding: 20px;
    }

    h2 {
      text-align: center;
      color: gold;
      margin-bottom: 20px;
    }

    form {
      background-color: #222;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(255, 215, 0, 0.2);
    }

    label {
      display: block;
      margin-top: 12px;
      font-size: 15px;
      color: #f1f1f1;
    }

    input[type="text"],
    input[type="email"],
    input[type="date"],
    input[type="number"] {
      width: 100%;
      padding: 10px;
      margin-top: 6px;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      background-color: #333;
      color: white;
    }

    input[type="submit"] {
      margin-top: 20px;
      width: 100%;
      background-color: gold;
      color: #111;
      font-weight: bold;
      border: none;
      padding: 12px;
      border-radius: 6px;
      font-size: 18px;
      cursor: pointer;
    }

    .mensaje {
      text-align: center;
      margin-top: 15px;
      font-weight: bold;
      color: lightgreen;
    }

    .error {
      color: red;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Registro Online</h2>

    <?php if (isset($_SESSION['mensaje'])): ?>
      <div class="mensaje"><?= $_SESSION['mensaje'] ?></div>
      <?php unset($_SESSION['mensaje']); ?>
    <?php endif; ?>

    <form action="registro_online_guardar.php" method="POST">
      <label>Apellido:</label>
      <input type="text" name="apellido" required>

      <label>Nombre:</label>
      <input type="text" name="nombre" required>

      <label>DNI:</label>
      <input type="text" name="dni" required>

      <label>Fecha de nacimiento:</label>
      <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" onchange="calcularEdad()" required>

      <label>Edad:</label>
      <input type="number" name="edad" id="edad" readonly required>

      <label>Domicilio:</label>
      <input type="text" name="domicilio" required>

      <label>Email:</label>
      <input type="email" name="email">

      <label>Teléfono:</label>
      <input type="text" name="telefono">

      <label>RFID:</label>
      <input type="text" name="rfid" required>

      <input type="submit" value="Registrarse">
    </form>
  </div>

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
