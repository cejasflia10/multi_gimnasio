<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$gimnasio_id = $_SESSION['gimnasio_id'];

if (!isset($_GET['id'])) {
    die("ID de turno no especificado.");
}

$id = intval($_GET['id']);

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_dia = $_POST['id_dia'];
    $id_horario = $_POST['id_horario'];
    $id_profesor = $_POST['id_profesor'];
    $cupo_maximo = $_POST['cupo_maximo'];

    $stmt = $conexion->prepare("UPDATE turnos SET id_dia = ?, id_horario = ?, id_profesor = ?, cupo_maximo = ? WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param("iiiiii", $id_dia, $id_horario, $id_profesor, $cupo_maximo, $id, $gimnasio_id);
    $stmt->execute();

    header("Location: ver_turnos.php");
    exit;
}

// Cargar datos actuales del turno
$turno = $conexion->query("SELECT * FROM turnos WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
$dias = $conexion->query("SELECT * FROM dias");
$horarios = $conexion->query("SELECT * FROM horarios");
$profesores = $conexion->query("SELECT * FROM profesores WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Turno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            padding: 40px;
        }
        form {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
        }
        label, select, input {
            display: block;
            width: 100%;
            margin-bottom: 15px;
            font-size: 16px;
        }
        input[type="submit"] {
            background-color: gold;
            color: black;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
        }
        a {
            color: gold;
            text-align: center;
            display: block;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Editar Turno</h2>

        <label>Día:</label>
        <select name="id_dia" required>
            <?php while ($d = $dias->fetch_assoc()) {
                $selected = $d['id'] == $turno['id_dia'] ? 'selected' : '';
                echo "<option value='{$d['id']}' $selected>{$d['nombre']}</option>";
            } ?>
        </select>

        <label>Horario:</label>
        <select name="id_horario" required>
            <?php while ($h = $horarios->fetch_assoc()) {
                $rango = substr($h['hora_inicio'], 0, 5) . ' - ' . substr($h['hora_fin'], 0, 5);
                $selected = $h['id'] == $turno['id_horario'] ? 'selected' : '';
                echo "<option value='{$h['id']}' $selected>$rango</option>";
            } ?>
        </select>

        <label>Profesor:</label>
        <select name="id_profesor" required>
            <?php while ($p = $profesores->fetch_assoc()) {
                $nombre = $p['apellido'] . ' ' . $p['nombre'];
                $selected = $p['id'] == $turno['id_profesor'] ? 'selected' : '';
                echo "<option value='{$p['id']}' $selected>$nombre</option>";
            } ?>
        </select>

        <label>Cupo máximo:</label>
        <input type="number" name="cupo_maximo" value="<?= $turno['cupo_maximo'] ?>" min="1">

        <input type="submit" value="Guardar Cambios">
        <a href="ver_turnos.php">← Volver</a>
    </form>
</body>
</html>
