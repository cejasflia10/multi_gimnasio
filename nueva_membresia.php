
<?php
include 'conexion.php';
$profesores = $conexion->query("SELECT id, nombre, apellido FROM profesores");
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales");
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas");
$planes = $conexion->query("SELECT id, nombre, precio FROM planes");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nueva Membresía</title>
  <link rel="stylesheet" href="style.css">
</head>
<body style="background-color:#111; color:#f1f1f1;">
  <div class="container" style="margin-left:260px; padding:20px;">
    <h2 style="color:gold;">Registrar Nueva Membresía</h2>
    <form action="guardar_membresia.php" method="POST">

      <label for="dni_buscar">Buscar Cliente por DNI:</label>
      <input type="text" name="dni_buscar" id="dni_buscar" placeholder="Escriba DNI..." autocomplete="off">

      <div id="resultado_cliente"></div>

      <label for="plan_id">Plan de Membresía:</label>
      <select name="plan_id" required>
        <option value="">Seleccionar plan</option>
        <?php while ($plan = $planes->fetch_assoc()): ?>
          <option value="<?= $plan['id'] ?>"><?= $plan['nombre'] ?> - $<?= $plan['precio'] ?></option>
        <?php endwhile; ?>
      </select>

      <label for="adicional_id">Adicional (opcional):</label>
      <select name="adicional_id">
        <option value="">Seleccionar adicional</option>
        <?php while ($ad = $adicionales->fetch_assoc()): ?>
          <option value="<?= $ad['id'] ?>"><?= $ad['nombre'] ?> - $<?= $ad['precio'] ?></option>
        <?php endwhile; ?>
      </select>

      <label for="disciplina_id">Disciplina:</label>
      <select name="disciplina_id" required>
        <option value="">Seleccionar disciplina</option>
        <?php while ($dis = $disciplinas->fetch_assoc()): ?>
          <option value="<?= $dis['id'] ?>"><?= $dis['nombre'] ?></option>
        <?php endwhile; ?>
      </select>

      <label for="profesor_id">Profesor (opcional):</label>
      <select name="profesor_id">
        <option value="">Seleccionar profesor</option>
        <?php while ($profe = $profesores->fetch_assoc()): ?>
          <option value="<?= $profe['id'] ?>"><?= $profe['apellido'] ?> <?= $profe['nombre'] ?></option>
        <?php endwhile; ?>
      </select>

      <label for="fecha_inicio">Fecha de Inicio:</label>
      <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>">

      <button type="submit" style="background-color:gold; color:#000; font-weight:bold;">Guardar Membresía</button>
    </form>
  </div>

  <script>
    document.getElementById("dni_buscar").addEventListener("input", function() {
      let dni = this.value;
      if (dni.length >= 3) {
        fetch("buscar_cliente.php?dni=" + dni)
          .then(response => response.text())
          .then(data => {
            document.getElementById("resultado_cliente").innerHTML = data;
          });
      } else {
        document.getElementById("resultado_cliente").innerHTML = "";
      }
    });
  </script>
</body>
</html>
