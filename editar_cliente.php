<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['id'])) {
    echo "<div class='error'>ID de cliente no proporcionado.</div>";
    exit;
}

$id = intval($_GET['id']);
$cliente = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM clientes WHERE id = $id"));
if (!$cliente) {
    echo "<div class='error'>Cliente no encontrado.</div>";
    exit;
}

$es_admin = ($_SESSION['rol'] ?? '') === 'admin';

// Cargar disciplinas por gimnasio
$gimnasio_id_cliente = $cliente['gimnasio_id'] ?? 0;
$disciplinas = mysqli_query($conexion, "SELECT * FROM disciplinas WHERE gimnasio_id = $gimnasio_id_cliente");

// Si es admin, cargar gimnasios
if ($es_admin) {
    $gimnasios = mysqli_query($conexion, "SELECT * FROM gimnasios");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>✏️ Editar Cliente</h2>

    <form action="guardar_edicion_cliente.php" method="POST">
        <input type="hidden" name="id" value="<?= $cliente['id']; ?>">

        <label>Apellido:</label>
        <input type="text" name="apellido" value="<?= htmlspecialchars($cliente['apellido']); ?>" required>

        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($cliente['nombre']); ?>" required>

        <label>DNI:</label>
        <input type="text" name="dni" value="<?= htmlspecialchars($cliente['dni']); ?>" required>

        <label>Fecha de nacimiento:</label>
        <input type="date" name="fecha_nacimiento" value="<?= $cliente['fecha_nacimiento']; ?>">

        <label>Domicilio:</label>
        <input type="text" name="domicilio" value="<?= htmlspecialchars($cliente['domicilio']); ?>">

        <label>Teléfono:</label>
        <input type="text" name="telefono" value="<?= htmlspecialchars($cliente['telefono']); ?>">

        <label>Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($cliente['email']); ?>">

        <label>Disciplina:</label>
        <select name="disciplina" required>
            <option value="">Seleccionar disciplina</option>
            <?php while ($row = mysqli_fetch_assoc($disciplinas)): ?>
                <option value="<?= $row['nombre']; ?>" <?= $row['nombre'] == $cliente['disciplina'] ? 'selected' : ''; ?>>
                    <?= $row['nombre']; ?>
                </option>
            <?php endwhile; ?>
        </select>

        <?php if ($es_admin): ?>
        <label>Gimnasio:</label>
        <select name="gimnasio_id">
            <option value="">Seleccionar gimnasio</option>
            <?php while ($g = mysqli_fetch_assoc($gimnasios)): ?>
                <option value="<?= $g['id']; ?>" <?= $g['id'] == $cliente['gimnasio_id'] ? 'selected' : ''; ?>>
                    <?= $g['nombre']; ?>
                </option>
            <?php endwhile; ?>
        </select>
        <?php endif; ?>

        <button type="submit">Guardar Cambios</button>
        <br><br>
        <a href="ver_clientes.php" style="color:#ffd600;">⬅ Volver al listado</a>
    </form>
</div>
</body>
</html>
