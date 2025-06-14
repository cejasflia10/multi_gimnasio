<?php
include 'conexion.php';

// Guardar turno laboral
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profesor_id'])) {
    $id = $_POST['profesor_id'];
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $conexion->query("INSERT INTO turnos (profesor_id, dia, hora_inicio, hora_fin) VALUES ('$id', '$dia', '$hora_inicio', '$hora_fin')");
    header("Location: ver_profesores.php");
    exit();
}

$profesores = $conexion->query("SELECT * FROM profesores ORDER BY apellido ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ver Profesores</title>
  <style>
    body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
    .container { padding: 30px; }
    h1 { color: #ffc107; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 10px; border-bottom: 1px solid #333; text-align: left; }
    th { background: #222; color: #ffc107; }
    .btn { padding: 5px 10px; background: #ffc107; color: #111; text-decoration: none; border-radius: 5px; }
    .btn:hover { background: #e0a800; }
    .form-turno { margin-top: 10px; background: #222; padding: 10px; border-radius: 5px; }
    select, input[type='time'] { margin: 5px 0; padding: 5px; width: 100%; }
  </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
  <h1>Profesores</h1>
  <table>
    <tr>
      <th>Apellido y Nombre</th>
      <th>Teléfono</th>
      <th>Domicilio</th>
      <th>RFID</th>
      <th>Turnos</th>
    </tr>
    <?php while ($p = $profesores->fetch_assoc()): ?>
    <tr>
      <td><?= $p['apellido'] ?> <?= $p['nombre'] ?></td>
      <td><?= $p['telefono'] ?></td>
      <td><?= $p['domicilio'] ?></td>
      <td><?= $p['rfid'] ?></td>
      <td>
        <form class="form-turno" method="POST">
          <input type="hidden" name="profesor_id" value="<?= $p['id'] ?>">
          <label>Día:</label>
          <select name="dia" required>
            <option value="Lunes">Lunes</option>
            <option value="Martes">Martes</option>
            <option value="Miércoles">Miércoles</option>
            <option value="Jueves">Jueves</option>
            <option value="Viernes">Viernes</option>
            <option value="Sábado">Sábado</option>
          </select>
          <label>Hora Inicio:</label>
          <input type="time" name="hora_inicio" required>
          <label>Hora Fin:</label>
          <input type="time" name="hora_fin" required>
          <button class="btn" type="submit">Guardar Turno</button>
        </form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>
</body>
</html>
