<?php
include 'conexion.php';
include 'menu_horizontal.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Insertar turno si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['profesor_id'])) {
    $profesor_id = $_POST["profesor_id"];
    $dia = $_POST["dia"];
    $hora_inicio = $_POST["hora_inicio"];
    $hora_fin = $_POST["hora_fin"];

    $stmt = $conexion->prepare("INSERT INTO turnos_profesor (profesor_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $profesor_id, $dia, $hora_inicio, $hora_fin);
    $stmt->execute();
    $stmt->close();
}

// Eliminar turno
if (isset($_GET['eliminar'])) {
    $id_turno = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM turnos_profesor WHERE id = $id_turno");
    header("Location: turnos_profesor.php");
    exit();
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
    input, select, button { padding: 10px; margin: 5px; border: none; border-radius: 5px; }
    button { background-color: gold; color: #000; font-weight: bold; cursor: pointer; }
    table { width: 100%; margin-top: 30px; border-collapse: collapse; color: white; }
    th, td { border: 1px solid gold; padding: 10px; }
    th { background-color: #222; }
    a.boton { color: gold; text-decoration: none; margin: 0 5px; font-weight: bold; }
    a.boton:hover { text-decoration: underline; }
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
    <tr>
      <th>Profesor</th>
      <th>Día</th>
      <th>Hora Inicio</th>
      <th>Hora Fin</th>
      <th>Acciones</th>
    </tr>
    <?php while ($t = $turnos->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($t['apellido'] . ' ' . $t['nombre']) ?></td>
        <td><?= htmlspecialchars($t['dia']) ?></td>
        <td><?= htmlspecialchars($t['hora_inicio']) ?></td>
        <td><?= htmlspecialchars($t['hora_fin']) ?></td>
        <td>
          <a class="boton" href="editar_turno_profesor.php?id=<?= $t['id'] ?>">✏️ Editar</a>
          <a class="boton" href="turnos_profesor.php?eliminar=<?= $t['id'] ?>" onclick="return confirm('¿Eliminar este turno?')">🗑️ Eliminar</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>

</body>
</html>
