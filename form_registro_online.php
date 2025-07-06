<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro Online - Cliente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
  <h2>📋 Registro Online de Cliente</h2>

  <form action="https://multi-gimnasio-1.onrender.com/registrar_cliente_online.php" method="POST">
    <label>Apellido:</label>
    <input type="text" name="apellido" required>

    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <label>DNI:</label>
    <input type="text" name="dni" required>

    <label>Fecha de nacimiento:</label>
    <input type="date" name="fecha_nacimiento" required>

    <label>Domicilio:</label>
    <input type="text" name="domicilio">

    <label>Teléfono:</label>
    <input type="text" name="telefono">

    <label>Correo electrónico:</label>
    <input type="email" name="email">

    <label>Disciplina:</label>
    <select name="disciplina" required>
      <option value="">Seleccionar</option>
      <option value="Boxeo">Boxeo</option>
      <option value="Kickboxing">Kickboxing</option>
      <option value="MMA">MMA</option>
      <option value="Funcional">Funcional</option>
    </select>

    <label>Academia:</label>
    <select name="gimnasio_id" required>
      <option value="">Seleccionar gimnasio</option>
      <option value="1">Fight Academy Centro</option>
      <option value="2">Fight Academy Norte</option>
      <option value="3">Fight Academy Sur</option>
    </select>

    <button type="submit">✅ Registrar Cliente</button>
  </form>
</div>
</body>
</html>
