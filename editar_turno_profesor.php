<?php
include 'conexion.php';
include 'menu_horizontal.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

// Obtener datos del turno
$turno = $conexion->query("SELECT * FROM turnos_profesor WHERE id = $id")->fetch_assoc();
if (!$turno) {
    die("Turno no encontrado.");
}

// Obtener listado de profesores
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores");

// Procesar actualización
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $profesor_id = $_POST["profesor_id"];
    $dia = $_POST["dia"];
    $hora_inicio = $_POST["hora_inicio"];
    $hora_fin = $_POST["hora_fin"];

    $stmt = $conexion->prepare("UPDATE turnos_profesor SET profesor_id=?, dia=?, hora_inicio=?, hora_fin=? WHERE id=?");
    $stmt->bind_param("isssi", $profesor_id, $dia, $hora_inicio, $hora_fin, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: turnos_profesor.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Turno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h2>✏️ Editar Turno de Profesor</h2>

    <form method="POST">
        <label>Profesor:</label>
        <select name="profesor_id" required>
            <?php while ($p = $profesores->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id'] == $turno['profesor_id'] ? 'selected' : '' ?>>
                    <?= $p['apellido'] . ' ' . $p['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Día:</label>
        <select name="dia" required>
            <?php
            $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            foreach ($dias as $d) {
                $selected = ($d == $turno['dia']) ? "selected" : "";
                echo "<option value='$d' $selected>$d</option>";
            }
            ?>
        </select>

        <label>Hora Inicio:</label>
        <input type="time" name="hora_inicio" value="<?= $turno['hora_inicio'] ?>" required>

        <label>Hora Fin:</label>
        <input type="time" name="hora_fin" value="<?= $turno['hora_fin'] ?>" required>

        <button type="submit">💾 Guardar Cambios</button>
    </form>

    <br>
    <a href="turnos_profesor.php" style="color:#ffd600;">← Volver a Turnos</a>
</div>

</body>
</html>
