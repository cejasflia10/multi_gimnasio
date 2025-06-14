<?php
include 'conexion.php';

$mensaje = "";
if (isset($_POST['profesor_id'])) {
    $profesor_id = $_POST['profesor_id'];
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];

    $stmt = $conexion->prepare("INSERT INTO turnos (profesor_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $profesor_id, $dia, $hora_inicio, $hora_fin);
    $stmt->execute();

    $mensaje = "Turno registrado correctamente.";
}

// Obtener profesores
$profesores = $conexion->query("SELECT id, nombre, apellido FROM profesores ORDER BY apellido");

// Obtener turnos
$turnos = $conexion->query("SELECT t.*, p.nombre, p.apellido FROM turnos t JOIN profesores p ON t.profesor_id = p.id ORDER BY t.dia, t.hora_inicio");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Turnos Profesores</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        label, select, input { display: block; margin-top: 10px; padding: 8px; width: 100%; }
        .btn { margin-top: 15px; padding: 10px; background: #ffc107; color: #111; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #e0a800; }
        table { margin-top: 30px; width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid #444; text-align: center; }
        th { color: #ffc107; }
        .mensaje { color: #0f0; margin-top: 15px; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Turnos Laborales de Profesores</h1>

    <form method="POST">
        <label>Seleccionar Profesor:</label>
        <select name="profesor_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while ($p = $profesores->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>"><?= $p['apellido'] ?> <?= $p['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Día:</label>
        <select name="dia" required>
            <option value="Lunes">Lunes</option>
            <option value="Martes">Martes</option>
            <option value="Miércoles">Miércoles</option>
            <option value="Jueves">Jueves</option>
            <option value="Viernes">Viernes</option>
            <option value="Sábado">Sábado</option>
        </select>

        <label>Hora de Inicio:</label>
        <input type="time" name="hora_inicio" required>

        <label>Hora de Fin:</label>
        <input type="time" name="hora_fin" required>

        <button type="submit" class="btn">Agregar Turno</button>
    </form>

    <?php if ($mensaje): ?>
        <p class="mensaje"><?= $mensaje ?></p>
    <?php endif; ?>

    <h2>Turnos Registrados</h2>
    <table>
        <tr>
            <th>Profesor</th>
            <th>Día</th>
            <th>Inicio</th>
            <th>Fin</th>
        </tr>
        <?php while ($t = $turnos->fetch_assoc()): ?>
        <tr>
            <td><?= $t['apellido'] ?> <?= $t['nombre'] ?></td>
            <td><?= $t['dia'] ?></td>
            <td><?= $t['hora_inicio'] ?></td>
            <td><?= $t['hora_fin'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
