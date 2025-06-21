<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado");
}
$gimnasio_id = $_SESSION['gimnasio_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['guardar_turno'])) {
    $profesor_id = $_POST['profesor_id'];
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];

    $stmt = $conexion->prepare("INSERT INTO turnos_profesor (profesor_id, gimnasio_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $profesor_id, $gimnasio_id, $dia, $hora_inicio, $hora_fin);
    $stmt->execute();
    $stmt->close();
}

$profesores = $conexion->query("SELECT id, apellido, nombre, domicilio, rfid FROM profesores WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background-color: #111; color: #f1f1f1; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        h1 { color: gold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th, td { border: 1px solid #555; padding: 8px; text-align: center; }
        th { background-color: #222; color: gold; }
        .btn { padding: 6px 12px; margin: 2px; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .editar { background-color: #4CAF50; }
        .eliminar { background-color: #f44336; }
        .turno { background-color: #2196F3; }
        .volver { background-color: #555; display: inline-block; margin-top: 20px; text-decoration: none; }
        form { margin-top: 20px; }
        select, input[type=time] { padding: 6px; width: 100%; margin: 4px 0; }
    </style>
</head>
<body>

<h1>Listado de Profesores</h1>

<table>
    <tr>
        <th>Apellido</th>
        <th>Nombre</th>
        <th>Domicilio</th>
        <th>RFID</th>
        <th>Acciones</th>
    </tr>
    <?php while ($row = $profesores->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['apellido']) ?></td>
        <td><?= htmlspecialchars($row['nombre']) ?></td>
        <td><?= htmlspecialchars($row['domicilio']) ?></td>
        <td><?= htmlspecialchars($row['rfid']) ?></td>
        <td>
            <a href="editar_profesor.php?id=<?= $row['id'] ?>" class="btn editar">Editar</a>
            <a href="eliminar_profesor.php?id=<?= $row['id'] ?>" class="btn eliminar" onclick="return confirm('¿Seguro que deseas eliminar?')">Eliminar</a>
        </td>
    </tr>
    <tr>
        <td colspan="5">
            <form method="POST">
                <input type="hidden" name="profesor_id" value="<?= $row['id'] ?>">
                <label>Día:
                    <select name="dia">
                        <option value="Lunes">Lunes</option>
                        <option value="Martes">Martes</option>
                        <option value="Miércoles">Miércoles</option>
                        <option value="Jueves">Jueves</option>
                        <option value="Viernes">Viernes</option>
                        <option value="Sábado">Sábado</option>
                    </select>
                </label>
                <label>Hora Inicio:
                    <input type="time" name="hora_inicio" required>
                </label>
                <label>Hora Fin:
                    <input type="time" name="hora_fin" required>
                </label>
                <button type="submit" name="guardar_turno" class="btn turno">Guardar Turno</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<a href="index.php" class="btn volver">Volver al menú</a>

</body>
</html>
