
<?php
include 'conexion.php';
$profesores = $conexion->query("SELECT id, nombre, apellido FROM profesores");
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales");
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas");
$planes = $conexion->query("SELECT id, nombre, precio FROM planes");
$clientes = $conexion->query("SELECT id, nombre, apellido FROM clientes");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nueva Membresía</title>
  <style>
    body { background-color: #111; color: #f1f1f1; font-family: Arial, sans-serif; }
    .form-container { width: 90%; max-width: 900px; margin: 40px auto; padding: 20px; background-color: #222; border-radius: 10px; }
    input, select, button { width: 100%; padding: 10px; margin-top: 10px; border: none; border-radius: 5px; }
    label { display: block; margin-top: 20px; font-weight: bold; }
    button { background-color: gold; color: #000; font-weight: bold; cursor: pointer; }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Registrar Nueva Membresía</h2>
    <form action="guardar_membresia.php" method="POST">

      <label>Cliente:</label>
      <select name="cliente_id" required>
        <option value="">Seleccionar cliente</option>
        <?php while ($cli = $clientes->fetch_assoc()): ?>
          <option value="<?= $cli['id'] ?>"><?= $cli['apellido'] ?> <?= $cli['nombre'] ?></option>
        <?php endwhile; ?>
      </select>

      <label>Plan de Membresía:</label>
      <select name="plan_id" required>
        <option value="">Seleccionar plan</option>
        <?php while ($plan = $planes->fetch_assoc()): ?>
          <option value="<?= $plan['id'] ?>"><?= $plan['nombre'] ?> ($<?= $plan['precio'] ?>)</option>
        <?php endwhile; ?>
      </select>

      <label>Plan Adicional:</label>
      <select name="adicional_id">
        <option value="">(Opcional) Seleccionar adicional</option>
        <?php while ($ad = $adicionales->fetch_assoc()): ?>
          <option value="<?= $ad['id'] ?>"><?= $ad['nombre'] ?> ($<?= $ad['precio'] ?>)</option>
        <?php endwhile; ?>
      </select>

      <label>Disciplina:</label>
      <select name="disciplina_id" required>
        <option value="">Seleccionar disciplina</option>
        <?php while ($dis = $disciplinas->fetch_assoc()): ?>
          <option value="<?= $dis['id'] ?>"><?= $dis['nombre'] ?></option>
        <?php endwhile; ?>
      </select>

      <label>Profesor:</label>
      <select name="profesor_id">
        <option value="">(Opcional) Seleccionar profesor</option>
        <?php while ($profe = $profesores->fetch_assoc()): ?>
          <option value="<?= $profe['id'] ?>"><?= $profe['apellido'] ?> <?= $profe['nombre'] ?></option>
        <?php endwhile; ?>
      </select>

      <label>Fecha de Inicio:</label>
      <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>">

      <button type="submit">Guardar Membresía</button>
    </form>
  </div>
</body>
</html>
