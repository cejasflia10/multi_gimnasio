<?php
session_start();
include 'conexion.php';

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['profesor', 'admin'])) {
    die("Acceso denegado.");
}

$cliente_id = $_GET['id'] ?? null;
if (!$cliente_id) die("ID de cliente requerido.");

// Agregar graduación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grado'])) {
    $disciplina = $_POST['disciplina'];
    $grado = $_POST['grado'];
    $fecha_examen = $_POST['fecha_examen'];
    $observaciones = $_POST['observaciones'];
    $profesor_id = $_SESSION['usuario_id'] ?? 0;

    $stmt = $conexion->prepare("INSERT INTO graduaciones_cliente (cliente_id, disciplina, grado, fecha_examen, observaciones, profesor_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $cliente_id, $disciplina, $grado, $fecha_examen, $observaciones, $profesor_id);
    $stmt->execute();
    $stmt->close();
}

// Eliminar graduación
if (isset($_GET['eliminar'])) {
    $id_grado = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM graduaciones_cliente WHERE id = $id_grado AND cliente_id = $cliente_id");
    header("Location: editar_graduaciones.php?id=$cliente_id");
    exit();
}

$graduaciones = $conexion->query("SELECT * FROM graduaciones_cliente WHERE cliente_id = $cliente_id ORDER BY fecha_examen DESC");
$cliente = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = $cliente_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Graduaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>🥋 Graduaciones de <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

    <a class="btn-nueva" href="panel_cliente.php?id=<?= $cliente_id ?>">← Volver al Panel del Cliente</a>

    <form method="POST">
        <label>Disciplina:</label>
        <select name="disciplina" required>
            <option value="Kickboxing">Kickboxing</option>
            <option value="K1">K1</option>
            <option value="Muay Thai">Muay Thai</option>
        </select>

        <label>Grado / Cinturón:</label>
        <input type="text" name="grado" placeholder="Ej: Blanco, Amarillo" required>

        <label>Fecha de Examen:</label>
        <input type="date" name="fecha_examen" required>

        <label>Observaciones:</label>
        <textarea name="observaciones" rows="2"></textarea>

        <button type="submit">Agregar Graduación</button>
    </form>

    <h3>🎓 Graduaciones Registradas</h3>
    <table>
        <thead>
            <tr>
                <th>Disciplina</th>
                <th>Grado</th>
                <th>Fecha</th>
                <th>Observaciones</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($g = $graduaciones->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($g['disciplina']) ?></td>
                    <td><?= htmlspecialchars($g['grado']) ?></td>
                    <td><?= htmlspecialchars($g['fecha_examen']) ?></td>
                    <td><?= htmlspecialchars($g['observaciones']) ?></td>
                    <td><a class="eliminar" href="?id=<?= $cliente_id ?>&eliminar=<?= $g['id'] ?>" onclick="return confirm('¿Eliminar esta graduación?')">🗑️</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
