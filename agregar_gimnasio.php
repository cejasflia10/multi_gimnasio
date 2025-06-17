<?php include 'menu.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Nuevo Gimnasio</title>
  <style>
    body {
      background-color: #111;
      color: #ffc107;
      font-family: 'Segoe UI', sans-serif;
      text-align: center;
      padding: 50px;
    }
    form {
      background-color: #222;
      padding: 30px;
      border-radius: 10px;
      display: inline-block;
      max-width: 500px;
      width: 100%;
    }
    input, select {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border: none;
      border-radius: 5px;
      background-color: #333;
      color: #fff;
    }
    label {
      display: block;
      text-align: left;
      margin-top: 15px;
      font-weight: bold;
    }
    button {
      background-color: #ffc107;
      color: black;
      border: none;
      padding: 12px 20px;
      font-weight: bold;
      margin-top: 15px;
      cursor: pointer;
      width: 100%;
    }
    button:hover {
      background-color: #ffb100;
    }
  </style>
</head>
<body>

<h2>Agregar Nuevo Gimnasio</h2>

<form action="guardar_gimnasio.php" method="POST" enctype="multipart/form-data">
  <label for="nombre">Nombre del gimnasio:</label>
  <input type="text" name="nombre" required>

  <label for="logo">Logo del gimnasio:</label>
  <input type="file" name="logo" accept="image/*">

  <label for="direccion">Dirección:</label>
  <input type="text" name="direccion" required>

  <label for="telefono">Teléfono:</label>
  <input type="text" name="telefono" required>

  <label for="email">Email:</label>
  <input type="email" name="email" required>

  <label for="plan">Plan de gimnasio:</label>
  <select name="plan" required>
    <option value="">-- Seleccionar plan --</option>
    <option value="basico">Básico (30 días)</option>
    <option value="intermedio">Intermedio (60 días)</option>
    <option value="avanzado">Avanzado (90 días)</option>
  </select>

  <label for="monto">Monto a pagar:</label>
  <input type="number" name="monto" min="0" step="0.01" required>

  <label for="vencimiento">Fecha de vencimiento:</label>
  <input type="date" name="vencimiento" required>

  <button type="submit">Guardar Gimnasio</button>
</form>

</body>
</html>
