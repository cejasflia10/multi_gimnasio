<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro Online - Cliente</title>
  <style>
    body {
      background-color: #111;
      color: #f1c40f;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    form {
      max-width: 600px;
      margin: auto;
      background-color: #1a1a1a;
      padding: 20px;
      border-radius: 10px;
      border: 1px solid #f1c40f;
    }
    input, button {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      background-color: #222;
      color: #fff;
      border: 1px solid #f1c40f;
      border-radius: 5px;
    }
    button {
      background-color: #f1c40f;
      color: #000;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <h2 style="text-align:center;">Registro Online de Cliente</h2>
  <form action="https://multi-gimnasio-1.onrender.com/registrar_cliente_online.php" method="POST">
    <input type="text" name="apellido" placeholder="Apellido" required>
    <input type="text" name="nombre" placeholder="Nombre" required>
    <input type="text" name="dni" placeholder="DNI" required>
    <input type="date" name="fecha_nacimiento" required>
    <input type="text" name="domicilio" placeholder="Domicilio">
    <input type="text" name="telefono" placeholder="Teléfono">
    <input type="email" name="email" placeholder="Correo electrónico">
    <input type="text" name="rfid_uid" placeholder="RFID UID (opcional)">
    <button type="submit">Registrar Cliente</button>
  </form>
</body>
</html>
