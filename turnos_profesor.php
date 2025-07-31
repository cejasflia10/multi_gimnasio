<?php
ob_start();  // <-- Agrega esto para evitar problemas de header()
session_start();
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

include 'menu_horizontal.php';

// Insertar turno si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['profesor_id'])) {
    $profesor_id = $_POST["profesor_id"];
    $dia = $_POST["dia"];
    $hora_inicio = $_POST["hora_inicio"];
    $hora_fin = $_POST["hora_fin"];
    $cupo_maximo = 10;

    // Insertar en turnos_profesor
    $stmt1 = $conexion->prepare("INSERT INTO turnos_profesor (profesor_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
    $stmt1->bind_param("isss", $profesor_id, $dia, $hora_inicio, $hora_fin);
    $stmt1->execute();
    $stmt1->close();

    // Insertar en turnos_disponibles
    $stmt2 = $conexion->prepare("INSERT INTO turnos_disponibles (profesor_id, dia, hora_inicio, hora_fin, gimnasio_id, cupo_maximo) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt2->bind_param("isssii", $profesor_id, $dia, $hora_inicio, $hora_fin, $gimnasio_id, $cupo_maximo);
    $stmt2->execute();
    $stmt2->close();
}

// Eliminar turno
if (isset($_GET['eliminar'])) {
    $id_turno = intval($_GET['eliminar']);

    // Primero obtenemos el profesor_id de ese turno para eliminar la fila correcta en turnos_disponibles
    $resultado = $conexion->query("SELECT profesor_id FROM turnos_profesor WHERE id = $id_turno");
    if ($resultado && $fila = $resultado->fetch_assoc()) {
        $profesor_id_turno = $fila['profesor_id'];

        // Eliminamos el turno de turnos_profesor
        $conexion->query("DELETE FROM turnos_profesor WHERE id = $id_turno");

        // Eliminamos el turno correspondiente en turnos_disponibles usando profesor_id, dia, hora_inicio y hora_fin
        // Mejor eliminar solo el turno disponible que coincide con el mismo profesor, día y horario
        // Para eso obtenemos esos datos:
        $resultado2 = $conexion->query("SELECT dia, hora_inicio, hora_fin FROM turnos_profesor WHERE id = $id_turno");
        // Pero como ya eliminamos el turno anterior, hay que obtener esos datos antes de eliminar
        // Cambiamos el orden para evitar esto:
    }
    // Reordenamos para obtener datos antes de eliminar:
}
?>

<?php
// Reorganizando la eliminación para evitar borrar y perder datos antes de usarlos:
if (isset($_GET['eliminar'])) {
    $id_turno = intval($_GET['eliminar']);

    // Obtener datos del turno antes de borrar
    $resultado = $conexion->query("SELECT profesor_id, dia, hora_inicio, hora_fin FROM turnos_profesor WHERE id = $id_turno");
    if ($resultado && $fila = $resultado->fetch_assoc()) {
        $profesor_id_turno = $fila['profesor_id'];
        $dia_turno = $conexion->real_escape_string($fila['dia']);
        $hora_inicio_turno = $conexion->real_escape_string($fila['hora_inicio']);
        $hora_fin_turno = $conexion->real_escape_string($fila['hora_fin']);

        // Eliminar de turnos_profesor
        $conexion->query("DELETE FROM turnos_profesor WHERE id = $id_turno");

        // Eliminar el turno correspondiente en turnos_disponibles
        $conexion->query("DELETE FROM turnos_disponibles WHERE profesor_id = $profesor_id_turno AND dia = '$dia_turno' AND hora_inicio = '$hora_inicio_turno' AND hora_fin = '$hora_fin_turno'");

        header("Location: turnos_profesor.php");
        exit();
    }
}
?>

<?php
// Obtener profesores del gimnasio actual
$result = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id");

// Obtener turnos
$turnos = $conexion->query("
    SELECT t.*, p.apellido, p.nombre 
    FROM turnos_profesor t 
    JOIN profesores p ON t.profesor_id = p.id 
    WHERE p.gimnasio_id = $gimnasio_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Turnos de Profesores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
  <h1>🕓 Turnos de Profesores</h1>

  <form method="POST">
    <select name="profesor_id" required>
      <option value="">Seleccionar Profesor</option>
      <?php while ($row = $result->fetch_assoc()) {
        echo "<option value='".htmlspecialchars($row['id'])."'>".htmlspecialchars($row['apellido'].' '.$row['nombre'])."</option>";
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
</div>
</body>
</html>

<?php ob_end_flush(); // Finalizamos el buffer ?>
