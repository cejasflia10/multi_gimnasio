<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$gimnasio_id = $_SESSION['gimnasio_id'];

if (!isset($_GET['id'])) {
    die("ID de turno no especificado.");
}

$id = $_GET['id'];

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dia = $_POST['dia'];
    $horario_inicio = $_POST['horario_inicio'];
    $horario_fin = $_POST['horario_fin'];
    $profesor_id = $_POST['profesor_id'];

    $stmt = $conexion->prepare("UPDATE turnos SET dia = ?, horario_inicio = ?, horario_fin = ?, profesor_id = ? WHERE id = ?");
    $stmt->bind_param("sssii", $dia, $horario_inicio, $horario_fin, $profesor_id, $id);
    $stmt->execute();

    header("Location: ver_turnos.php");
    exit;
}

// Cargar datos actuales del turno
$turno = $conexion->query("SELECT * FROM turnos WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
$profesores = $conexion->query("SELECT id, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id");
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
        <select name="dia" required>
            <?php
            $dias = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];
            foreach ($dias as $d) {
                $selected = $turno['dia'] === $d ? 'selected' : '';
                echo "<option value='$d' $selected>$d</option>";
            }
            ?>
        </select>

        <label>Horario de Inicio:</label>
        <input type="time" name="horario_inicio" value="<?= $turno['horario_inicio'] ?>" required>

        <label>Horario de Fin:</label>
        <input type="time" name="horario_fin" value="<?= $turno['horario_fin'] ?>" required>

        <label>Profesor:</label>
        <select name="profesor_id" required>
            <?php while ($p = $profesores->fetch_assoc()) {
                $selected = $p['id'] == $turno['profesor_id'] ? 'selected' : '';
                echo "<option value='{$p['id']}' $selected>{$p['nombre']}</option>";
            } ?>
        </select>

        <input type="submit" value="Guardar Cambios">
        <a href="ver_turnos.php">← Volver</a>
    </form>
</body>
</html>
