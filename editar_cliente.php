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

// Cargar disciplinas filtradas por gimnasio del cliente
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
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            margin: 80px auto;
            max-width: 700px;
            padding: 20px;
            background-color: #1c1c1c;
            border-radius: 10px;
            box-shadow: 0 0 10px #000;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #f1c40f;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            margin-bottom: 16px;
            background-color: #222;
            color: #fff;
            border: 1px solid #f1c40f;
            border-radius: 4px;
        }
        label {
            font-weight: bold;
        }
        button {
            background-color: #f1c40f;
            color: #111;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            border-radius: 5px;
            width: 100%;
        }
        button:hover {
            background-color: #d4ac0d;
        }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>
<div class="container">
    <h2>Editar Cliente</h2>
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
    </form>
</div>
</body>
</html>
