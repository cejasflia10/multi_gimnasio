<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$id_gimnasio = $_GET['gimnasio'] ?? '';
$mensaje = $_GET['mensaje'] ?? '';

// Obtener disciplinas del gimnasio
$disciplinas = [];
if ($id_gimnasio !== '') {
    $consulta = $conexion->query("SELECT id, nombre FROM disciplinas WHERE id_gimnasio = $id_gimnasio");
    while ($row = $consulta->fetch_assoc()) {
        $disciplinas[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Cliente Online</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
        }
        form {
            max-width: 500px;
            margin: auto;
        }
        label {
            display: block;
            margin-top: 12px;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 4px;
            background-color: #222;
            color: white;
            border: 1px solid gold;
        }
        input[type="submit"] {
            background-color: gold;
            color: black;
            font-weight: bold;
            margin-top: 20px;
        }
        .mensaje {
            text-align: center;
            color: yellow;
            margin: 10px 0;
        }
    </style>
</head>
<body>

<h2>Registro de Cliente Online</h2>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form action="guardar_cliente_online.php" method="post">
    <input type="hidden" name="id_gimnasio" value="<?= htmlspecialchars($id_gimnasio) ?>">

    <label>Apellido:</label>
    <input type="text" name="apellido" required>

    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <label>DNI:</label>
    <input type="number" name="dni" required>

    <label>Fecha de nacimiento:</label>
    <input type="date" name="fecha_nacimiento" required>

    <label>Domicilio:</label>
    <input type="text" name="domicilio" required>

    <label>Teléfono:</label>
    <input type="text" name="telefono" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Disciplina:</label>
    <select name="disciplina" required>
        <option value="">-- Seleccionar disciplina --</option>
        <?php foreach ($disciplinas as $disciplina): ?>
            <option value="<?= htmlspecialchars($disciplina['nombre']) ?>"><?= htmlspecialchars($disciplina['nombre']) ?></option>
        <?php endforeach; ?>
    </select>

    <input type="submit" value="Registrar Cliente">
</form>

</body>
</html>
