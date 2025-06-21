<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apellido = $_POST["apellido"];
    $nombre = $_POST["nombre"];
    $domicilio = $_POST["domicilio"];
    $telefono = $_POST["telefono"];
    $rfid = $_POST["rfid"];
    $gimnasio_id = $_SESSION["gimnasio_id"];

    $sql = "INSERT INTO profesores (apellido, nombre, domicilio, telefono, rfid, gimnasio_id)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sssssi", $apellido, $nombre, $domicilio, $telefono, $rfid, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Profesor registrado'); window.location.href='ver_profesores.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Profesor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 40px 20px;
    }

    h1 {
      color: #f7d774;
      margin-bottom: 30px;
    }

    form {
      background-color: #1a1a1a;
      padding: 20px;
      border-radius: 10px;
      width: 100%;
      max-width: 500px;
      box-shadow: 0 0 15px #000;
    }

    label {
      display: block;
      margin-top: 15px;
      font-weight: bold;
    }

    input {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: none;
      border-radius: 5px;
      background-color: #333;
      color: #fff;
    }

    .botones {
      margin-top: 25px;
      display: flex;
      justify-content: space-between;
    }

    button, .volver {
      background-color: #f7d774;
      color: #111;
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      font-weight: bold;
      text-decoration: none;
      cursor: pointer;
    }

    button:hover, .volver:hover {
      background-color: #fff;
      color: #000;
    }

    @media (max-width: 600px) {
      .botones {
        flex-direction: column;
        gap: 10px;
      }
    }
  </style>
</head>
<body>

  <h1>Agregar Profesor</h1>

  <form method="POST">
    <label for="apellido">Apellido:</label>
    <input type="text" name="apellido" required>

    <label for="nombre">Nombre:</label>
    <input type="text" name="nombre" required>

    <label for="domicilio">Domicilio:</label>
    <input type="text" name="domicilio">

    <label for="telefono">Teléfono:</label>
    <input type="text" name="telefono">

    <label for="rfid">RFID:</label>
    <input type="text" name="rfid" required>

    <div class="botones">
      <button type="submit">Guardar</button>
      <a href="index.php" class="volver">Volver al Menú</a>
    </div>
  </form>

</body>
</html>
