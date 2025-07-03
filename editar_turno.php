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
    <style>
        body { background: black; color: gold; font-family: Arial; text-align: center; padding: 40px; }
        form { background: #111; padding: 20px; border-radius: 10px; display: inline-block; }
        input, select { padding: 8px; margin: 10px; width: 200px; border-radius: 5px; border: none; }
        button { padding: 10px 20px; background: gold; color: black; border: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>

<h2>✏️ Editar Turno</h2>

<form method="POST">
    <label>Día:<br>
        <select name="dia" required>
            <?php
            $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            foreach ($dias as $d) {
                $selected = ($turno['dia'] == $d) ? "selected" : "";
                echo "<option value='$d' $selected>$d</option>";
            }
            ?>
        </select>
    </label><br>
    <label>Hora Inicio:<br><input type="time" name="hora_inicio" value="<?= $turno['hora_inicio'] ?>" required></label><br>
    <label>Hora Fin:<br><input type="time" name="hora_fin" value="<?= $turno['hora_fin'] ?>" required></label><br>
    <label>Profesor:<br>
        <select name="profesor_id" required>
            <?php while ($p = $profesores->fetch_assoc()): 
                $sel = ($p['id'] == $turno['profesor_id']) ? "selected" : ""; ?>
                <option value="<?= $p['id'] ?>" <?= $sel ?>><?= $p['apellido'] ?> <?= $p['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
    </label><br>
    <label>Cupo máximo:<br><input type="number" name="cupo_maximo" value="<?= $turno['cupo_maximo'] ?>" required min="1" max="50"></label><br>
    <button type="submit">Guardar Cambios</button>
</form>

</body>
</html>
