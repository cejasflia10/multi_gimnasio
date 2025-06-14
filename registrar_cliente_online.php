<?php include 'conexion.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro Online</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      margin: 0;
      padding-top: 20px;
    }

    .logo {
      text-align: center;
      margin-bottom: 20px;
    }

    .logo img {
      height: 70px;
    }

    .container {
      max-width: 600px;
      margin: auto;
      background-color: #222;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(255, 193, 7, 0.2);
    }

    h2 {
      text-align: center;
      color: #ffc107;
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-top: 15px;
      color: #ffc107;
    }

    input[type="text"],
    input[type="number"],
    input[type="date"],
    input[type="email"] {
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      border: 1px solid #444;
      border-radius: 5px;
      background-color: #333;
      color: #fff;
    }

    input[type="submit"] {
      background-color: #ffc107;
      color: #111;
      border: none;
      padding: 12px 20px;
      margin-top: 20px;
      cursor: pointer;
      width: 100%;
      border-radius: 5px;
      font-weight: bold;
    }

    input[type="submit"]:hover {
      background-color: #e0a800;
    }
  </style>
</head>
<body>

  <div class="logo">
    <img src="logo.png" alt="Logo del Gimnasio">
  </div>

  <div class="container">
    <h2>Registro de Cliente Online</h2>
    <form method="post" action="guardar_cliente_online.php">
      <label for="apellido">Apellido:</label>
      <input type="text" name="apellido" required>

      <label for="nombre">Nombre:</label>
      <input type="text" name="nombre" required>

      <label for="dni">DNI:</label>
      <input type="text" name="dni" required>

      <label for="fecha_nacimiento">Fecha de nacimiento:</label>
      <input type="date" name="fecha_nacimiento">

      <label for="edad">Edad:</label>
      <input type="number" name="edad" min="1">

      <label for="domicilio">Domicilio:</label>
      <input type="text" name="domicilio">

      <label for="telefono">Teléfono:</label>
      <input type="text" name="telefono">

      <label for="email">Email:</label>
      <input type="email" name="email">

      <label for="rfid">RFID (si tiene):</label>
      <input type="text" name="rfid">

      
<tr>
    <td><label for="gimnasio_id">Gimnasio:</label></td>
    <td>
        <select name="gimnasio_id" required>
            <option value="">Seleccione un gimnasio</option>
            <?php
            include 'conexion.php';
            $resultado = $conexion->query("SELECT id, nombre FROM gimnasios");
            while($row = $resultado->fetch_assoc()) {
                echo "<option value='{$row['id']}'>{$row['nombre']}</option>";
            }
            ?>
        </select>
    </td>
</tr>

<input type="submit" value="Registrar Cliente">
    </form>
  </div>

</body>
</html>
