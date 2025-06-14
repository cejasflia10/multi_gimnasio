<?php
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID no especificado.");
}

$id = $_GET['id'];
$mensaje = "";

// Obtener datos actuales del turno
$consulta = $conexion->prepare("SELECT * FROM turnos WHERE id = ?");
$consulta->bind_param("i", $id);
$consulta->execute();
$resultado = $consulta->get_result();
$turno = $resultado->fetch_assoc();

if (!$turno) {
    die("Turno no encontrado.");
}

// Actualizar turno
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];

    $stmt = $conexion->prepare("UPDATE turnos SET dia = ?, hora_inicio = ?, hora_fin = ? WHERE id = ?");
    $stmt->bind_param("sssi", $dia, $hora_inicio, $hora_fin, $id);
    $stmt->execute();

    $mensaje = "Turno actualizado correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Turno</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding: 30px; }
        h1 { color: #ffc107; }
        label, select, input { display: block; margin-top: 10px; padding: 8px; width: 100%; }
        .btn { margin-top: 15px; padding: 10px; background: #ffc107; color: #111; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #e0a800; }
        .mensaje { color: #0f0; margin-top: 15px; }
    </style>
</head>
<body>
<h1>Editar Turno</h1>

<form method="POST">
    <label>Día:</label>
    <select name="dia" required>
        <option value="Lunes" <?= $turno['dia'] == 'Lunes' ? 'selected' : '' ?>>Lunes</option>
        <option value="Martes" <?= $turno['dia'] == 'Martes' ? 'selected' : '' ?>>Martes</option>
        <option value="Miércoles" <?= $turno['dia'] == 'Miércoles' ? 'selected' : '' ?>>Miércoles</option>
        <option value="Jueves" <?= $turno['dia'] == 'Jueves' ? 'selected' : '' ?>>Jueves</option>
        <option value="Viernes" <?= $turno['dia'] == 'Viernes' ? 'selected' : '' ?>>Viernes</option>
        <option value="Sábado" <?= $turno['dia'] == 'Sábado' ? 'selected' : '' ?>>Sábado</option>
    </select>

    <label>Hora de Inicio:</label>
    <input type="time" name="hora_inicio" value="<?= $turno['hora_inicio'] ?>" required>

    <label>Hora de Fin:</label>
    <input type="time" name="hora_fin" value="<?= $turno['hora_fin'] ?>" required>

    <button type="submit" class="btn">Guardar Cambios</button>
</form>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>
</body>
</html>
