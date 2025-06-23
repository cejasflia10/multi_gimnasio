<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Buscar datos del gimnasio
$logo = "default_logo.png"; // Logo por defecto
$nombre_gimnasio = "Gimnasio";

$resultado = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id = $gimnasio_id LIMIT 1");
if ($fila = $resultado->fetch_assoc()) {
    $nombre_gimnasio = $fila['nombre'];
    if (!empty($fila['logo'])) {
        $logo = $fila['logo'];
    }
}
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
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }

    .contenedor {
      max-width: 500px;
      margin: 0 auto;
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

    input, button {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: none;
      border-radius: 5px;
      font-size: 16px;
    }

    input {
      background-color: #333;
      color: white;
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

        <label>RFID (opcional):</label>
        <input type="text" name="rfid_uid">

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
