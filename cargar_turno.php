<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Insertar nuevo turno
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $profesor_id = $_POST['profesor_id'];
    $cupo_maximo = $_POST['cupo_maximo'];

    $conexion->query("INSERT INTO turnos_disponibles (dia, hora_inicio, hora_fin, profesor_id, gimnasio_id, cupo_maximo)
                      VALUES ('$dia', '$hora_inicio', '$hora_fin', $profesor_id, $gimnasio_id, $cupo_maximo)");
    header("Location: cargar_turno.php?ok=1");
    exit;
}

// Profesores disponibles
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE gimnasio_id = $gimnasio_id ORDER BY apellido");

// Turnos cargados
$turnos = $conexion->query("SELECT td.*, p.nombre, p.apellido
    FROM turnos_disponibles td
    JOIN profesores p ON td.profesor_id = p.id
    WHERE td.gimnasio_id = $gimnasio_id
    ORDER BY FIELD(td.dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'), td.hora_inicio");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar Turno</title>
    <style>
        body { background: black; color: gold; font-family: Arial; padding: 40px; text-align: center; }
        form { background: #111; padding: 20px; border-radius: 10px; display: inline-block; margin-top: 30px; }
        input, select { padding: 8px; margin: 10px; width: 200px; border-radius: 5px; border: none; }
        button { padding: 10px 20px; background: gold; color: black; border: none; border-radius: 5px; font-weight: bold; }
        table { width: 90%; margin: 40px auto; border-collapse: collapse; color: white; }
        th, td { border: 1px solid gold; padding: 10px; }
        th { background-color: #333; }
        td { background-color: #111; }
        a.btn { color: gold; font-weight: bold; text-decoration: none; }
    </style>
</head>
<body>

<h2>➕ Cargar Turno Disponible</h2>
<?php if (isset($_GET['ok'])): ?>
    <p style="color: lightgreen;">✅ Turno cargado correctamente</p>
<?php endif; ?>

<form method="POST">
    <label>Día:<br>
        <select name="dia" required>
            <option value="">Seleccione...</option>
            <option>Lunes</option>
            <option>Martes</option>
            <option>Miércoles</option>
            <option>Jueves</option>
            <option>Viernes</option>
            <option>Sábado</option>
        </select>
    </label><br>
    <label>Hora Inicio:<br><input type="time" name="hora_inicio" required></label><br>
    <label>Hora Fin:<br><input type="time" name="hora_fin" required></label><br>
    <label>Profesor:<br>
        <select name="profesor_id" required>
            <option value="">Seleccione...</option>
            <?php while ($p = $profesores->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>"><?= $p['apellido'] ?> <?= $p['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
    </label><br>
    <label>Cupo máximo:<br><input type="number" name="cupo_maximo" required min="1" max="50"></label><br>
    <button type="submit">Guardar Turno</button>
</form>

<h2>📋 Turnos Cargados</h2>
<table>
    <tr>
        <th>Día</th>
        <th>Hora</th>
        <th>Profesor</th>
        <th>Cupo</th>
        <th>Acciones</th>
    </tr>
    <?php while ($t = $turnos->fetch_assoc()): ?>
    <tr>
        <td><?= $t['dia'] ?></td>
        <td><?= substr($t['hora_inicio'], 0, 5) ?> - <?= substr($t['hora_fin'], 0, 5) ?></td>
        <td><?= $t['apellido'] . ' ' . $t['nombre'] ?></td>
        <td><?= $t['cupo_maximo'] ?></td>
        <td>
            <a href="editar_turno.php?id=<?= $t['id'] ?>" class="btn">✏️ Editar</a> |
            <a href="eliminar_turno.php?id=<?= $t['id'] ?>" class="btn" onclick="return confirm('¿Eliminar este turno?')">🗑️ Eliminar</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
