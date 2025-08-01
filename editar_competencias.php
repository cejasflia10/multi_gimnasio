<?php
session_start();
include 'conexion.php';

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['profesor', 'admin'])) {
    die("Acceso denegado.");
}

$cliente_id = $_GET['id'] ?? null;
if (!$cliente_id) die("ID de cliente requerido.");

// Agregar competencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['torneo'])) {
    $torneo = $_POST['torneo'];
    $categoria = $_POST['categoria'];
    $resultado = $_POST['resultado'];
    $fecha = $_POST['fecha'];
    $ciudad = $_POST['ciudad'];
    $profesor_id = $_SESSION['usuario_id'] ?? 0;

    $stmt = $conexion->prepare("INSERT INTO competencias_cliente (cliente_id, torneo, categoria, resultado, fecha, ciudad, profesor_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssi", $cliente_id, $torneo, $categoria, $resultado, $fecha, $ciudad, $profesor_id);
    $stmt->execute();
    $stmt->close();
}

// Eliminar competencia
if (isset($_GET['eliminar'])) {
    $id_comp = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM competencias_cliente WHERE id = $id_comp AND cliente_id = $cliente_id");
    header("Location: editar_competencias.php?id=$cliente_id");
    exit();
}

$competencias = $conexion->query("SELECT * FROM competencias_cliente WHERE cliente_id = $cliente_id ORDER BY fecha DESC");
$cliente = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = $cliente_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Competencias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>🥊 Competencias de <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

    <a class="btn-nueva" href="panel_cliente.php?id=<?= $cliente_id ?>">← Volver al Panel del Cliente</a>

    <form method="POST">
        <label>Torneo:</label>
        <input type="text" name="torneo" placeholder="Nombre del torneo" required>

        <label>Categoría:</label>
        <input type="text" name="categoria" placeholder="Ej: -60kg, Adulto" required>

        <label>Resultado:</label>
        <input type="text" name="resultado" placeholder="Ej: 1º puesto, participación" required>

        <label>Fecha:</label>
        <input type="date" name="fecha" required>

        <label>Ciudad:</label>
        <input type="text" name="ciudad" placeholder="Ciudad del evento">

        <button type="submit">Agregar Competencia</button>
    </form>

    <h3>📄 Competencias Registradas</h3>
    <table>
        <thead>
            <tr>
                <th>Torneo</th>
                <th>Fecha</th>
                <th>Categoría</th>
                <th>Resultado</th>
                <th>Ciudad</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($c = $competencias->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($c['torneo']) ?></td>
                    <td><?= htmlspecialchars($c['fecha']) ?></td>
                    <td><?= htmlspecialchars($c['categoria']) ?></td>
                    <td><?= htmlspecialchars($c['resultado']) ?></td>
                    <td><?= htmlspecialchars($c['ciudad']) ?></td>
                    <td><a class="eliminar" href="?id=<?= $cliente_id ?>&eliminar=<?= $c['id'] ?>" onclick="return confirm('¿Eliminar esta competencia?')">🗑️</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
