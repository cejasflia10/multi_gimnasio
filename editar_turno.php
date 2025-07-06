<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = $_GET['id'] ?? 0;

// Obtener turno
$turno = $conexion->query("SELECT * FROM turnos_disponibles WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
if (!$turno) die("Turno no encontrado");

// Profesores
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id ORDER BY apellido");

// Guardar cambios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $profesor_id = $_POST['profesor_id'];
    $cupo_maximo = $_POST['cupo_maximo'];

    $conexion->query("UPDATE turnos_disponibles SET 
        dia = '$dia',
        hora_inicio = '$hora_inicio',
        hora_fin = '$hora_fin',
        profesor_id = $profesor_id,
        cupo_maximo = $cupo_maximo
        WHERE id = $id AND gimnasio_id = $gimnasio_id");

    header("Location: cargar_turno.php?editado=1");
    exit;
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
    <h2>✏️ Editar Turno</h2>

    <form method="POST">
        <label>Día:</label>
        <select name="dia" required>
            <?php
            $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            foreach ($dias as $d) {
                $selected = ($turno['dia'] == $d) ? "selected" : "";
                echo "<option value='$d' $selected>$d</option>";
            }
            ?>
        </select>

        <label>Hora Inicio:</label>
        <input type="time" name="hora_inicio" value="<?= $turno['hora_inicio'] ?>" required>

        <label>Hora Fin:</label>
        <input type="time" name="hora_fin" value="<?= $turno['hora_fin'] ?>" required>

        <label>Profesor:</label>
        <select name="profesor_id" required>
            <?php while ($p = $profesores->fetch_assoc()):
                $sel = ($p['id'] == $turno['profesor_id']) ? "selected" : ""; ?>
                <option value="<?= $p['id'] ?>" <?= $sel ?>>
                    <?= $p['apellido'] . ' ' . $p['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Cupo máximo:</label>
        <input type="number" name="cupo_maximo" value="<?= $turno['cupo_maximo'] ?>" required min="1" max="50">

        <button type="submit">💾 Guardar Cambios</button>
    </form>

    <br>
    <a href="cargar_turno.php" style="color:#ffd600;">⬅ Volver a Turnos</a>
</div>

</body>
</html>
