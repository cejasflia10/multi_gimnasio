<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener logo y nombre del gimnasio
$logo = "default_logo.png";
$nombre_gimnasio = "Gimnasio";
$res = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id = $gimnasio_id LIMIT 1");
if ($fila = $res->fetch_assoc()) {
    $nombre_gimnasio = $fila['nombre'];
    if (!empty($fila['logo'])) $logo = $fila['logo'];
}

// Cargar disciplinas
$disciplinas = $conexion->query("SELECT nombre FROM disciplinas ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Registro Online - <?= $nombre_gimnasio ?></title>
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }
    .contenedor {
      max-width: 500px;
      margin: auto;
      padding: 20px;
    }
    .logo {
      text-align: center;
      padding: 20px 0;
    }
    .logo img {
      width: 120px;
      border-radius: 10px;
    }
    .titulo {
      text-align: center;
      font-size: 24px;
      color: gold;
      margin-bottom: 20px;
    }
    .formulario {
      background-color: #222;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 0 10px #000;
    }
    label {
      display: block;
      margin-top: 10px;
    }
    input, select, button {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: none;
      border-radius: 5px;
      font-size: 16px;
    }
    input, select {
      background-color: #333;
      color: gold;
    }
    button {
      background-color: gold;
      color: black;
      font-weight: bold;
      margin-top: 15px;
      cursor: pointer;
    }
    #respuesta {
      margin-top: 15px;
      text-align: center;
      font-weight: bold;
    }
    @media (max-width: 600px) {
      .contenedor {
        padding: 10px;
      }
    }
  </style>
</head>
<body>

  <div class="contenedor">
    <div class="logo">
      <img src="<?= $logo ?>" alt="Logo del gimnasio">
    </div>
    <div class="titulo"><?= $nombre_gimnasio ?></div>

    <div class="formulario">
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

        <label>Disciplina:</label>
        <select name="disciplina" required>
          <option value="">Seleccionar disciplina</option>
          <?php while($d = $disciplinas->fetch_assoc()): ?>
            <option value="<?= $d['nombre'] ?>"><?= $d['nombre'] ?></option>
          <?php endwhile; ?>
        </select>

        <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">

        <button type="submit">Registrar</button>
      </form>
      <div id="respuesta"></div>
    </div>
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
      .catch(() => {
        document.getElementById("respuesta").innerText = "Error al registrar.";
      });
    });
  </script>

</body>
</html>
