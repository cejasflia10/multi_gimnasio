<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas");
$academias = $conexion->query("SELECT id, nombre FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Cliente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background-color: #111;
      color: gold;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }

    .form-container {
      background-color: #222;
      padding: 20px;
      border-radius: 15px;
      width: 90%;
      max-width: 400px;
      box-shadow: 0 0 15px rgba(255, 215, 0, 0.3);
    }

    h2 {
      text-align: center;
      color: gold;
      margin-bottom: 20px;
    }

    label {
      color: gold;
      display: block;
      margin-top: 10px;
      margin-bottom: 5px;
    }

    input, select {
      width: 100%;
      padding: 10px;
      border: none;
      border-radius: 10px;
      background-color: #333;
      color: white;
      margin-bottom: 10px;
    }

    input:invalid {
      border: 1px solid red;
    }

    .btn {
      width: 100%;
      background-color: gold;
      color: black;
      border: none;
      border-radius: 10px;
      padding: 12px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      margin-top: 10px;
    }

    .btn:hover {
      background-color: #e0c100;
    }

    .small {
      font-size: 12px;
      color: #aaa;
      margin-top: -8px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>

<div class="form-container">
  <h2>Registro de Cliente</h2>
  <form action="guardar_cliente_online.php" method="POST">
    <label for="apellido">Apellido:</label>
    <input type="text" name="apellido" id="apellido" required>

    <label for="nombre">Nombre:</label>
    <input type="text" name="nombre" id="nombre" required>

    <label for="dni">DNI:</label>
    <input type="text" name="dni" id="dni" required>

    <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
    <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required>

    <label for="edad">Edad:</label>
    <input type="number" name="edad" id="edad" readonly>

    <label for="domicilio">Domicilio:</label>
    <input type="text" name="domicilio" id="domicilio" required>

    <label for="telefono">Teléfono:</label>
    <input type="tel" name="telefono" id="telefono" required>

    <label for="email">Email:</label>
    <input type="email" name="email" id="email" required>

    <label for="rfid_uid">RFID:</label>
    <input type="text" name="rfid_uid" id="rfid_uid" required>

    <label for="disciplina">Disciplina:</label>
    <select name="disciplina_id" id="disciplina_id" required>
      <option value="">Seleccione una disciplina</option>
      <?php while ($d = $disciplinas->fetch_assoc()) { ?>
        <option value="<?= $d['id'] ?>"><?= $d['nombre'] ?></option>
      <?php } ?>
    </select>

    <label for="gimnasio_id">Academia:</label>
    <select name="gimnasio_id" id="gimnasio_id" required>
      <option value="">Seleccione una academia</option>
      <?php while ($g = $academias->fetch_assoc()) { ?>
        <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
      <?php } ?>
    </select>

    <button type="submit" class="btn">Registrar</button>
  </form>
</div>

<script>
  // Edad automática
  document.getElementById('fecha_nacimiento').addEventListener('change', function () {
    const fechaNacimiento = new Date(this.value);
    const hoy = new Date();
    let edad = hoy.getFullYear() - fechaNacimiento.getFullYear();
    const m = hoy.getMonth() - fechaNacimiento.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < fechaNacimiento.getDate())) {
      edad--;
    }
    document.getElementById('edad').value = edad;
  });
</script>

</body>
</html>
