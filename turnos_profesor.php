<?php
include 'conexion.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Insertar turno si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $profesor_id = $_POST["profesor_id"];
    $dia = $_POST["dia"];
    $hora_inicio = $_POST["hora_inicio"];
    $hora_fin = $_POST["hora_fin"];

    $stmt = $conexion->prepare("INSERT INTO turnos_profesor (profesor_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $profesor_id, $dia, $hora_inicio, $hora_fin);
    $stmt->execute();
    $stmt->close();
}

$result = $conexion->query("SELECT id, apellido, nombre FROM profesores");
$turnos = $conexion->query("SELECT t.*, p.apellido, p.nombre FROM turnos_profesor t JOIN profesores p ON t.profesor_id = p.id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Turnos de Profesores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { background-color: #000; color: gold; font-family: Arial; text-align: center; padding: 20px; }
    input, select { padding: 10px; margin: 5px; }
    table { width: 100%; margin-top: 30px; border-collapse: collapse; color: white; }
    th, td { border: 1px solid gold; padding: 10px; }
    th { background-color: #222; }
  </style>
</head>
<body>
  <h1>🕓 Turnos de Profesores</h1>
  <form method="POST">
    <select name="profesor_id" required>
      <option value="">Seleccionar Profesor</option>
      <?php while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['id']}'>{$row['apellido']} {$row['nombre']}</option>";
      } ?>
    </select>
    <select name="dia" required>
      <option value="Lunes">Lunes</option><option value="Martes">Martes</option>
      <option value="Miércoles">Miércoles</option><option value="Jueves">Jueves</option>
      <option value="Viernes">Viernes</option><option value="Sábado">Sábado</option>
    </select>
    <input type="time" name="hora_inicio" required>
    <input type="time" name="hora_fin" required>
    <button type="submit">Agregar Turno</button>
  </form>

  <h2>Turnos Registrados</h2>
  <table>
    <tr><th>Profesor</th><th>Día</th><th>Hora Inicio</th><th>Hora Fin</th></tr>
    <?php while ($t = $turnos->fetch_assoc()) {
      echo "<tr><td>{$t['apellido']} {$t['nombre']}</td><td>{$t['dia']}</td><td>{$t['hora_inicio']}</td><td>{$t['hora_fin']}</td></tr>";
    } ?>
  </table>
</body>
</html>
