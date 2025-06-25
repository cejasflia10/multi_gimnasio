<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_GET['gimnasio'] ?? 0;

// Obtener disciplinas del gimnasio
$disciplinas = [];
if ($gimnasio_id) {
    $resultado = $conexion->query("SELECT id, nombre FROM disciplinas WHERE id_gimnasio = $gimnasio_id");
    while ($fila = $resultado->fetch_assoc()) {
        $disciplinas[] = $fila;
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
            color: gold;
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
            padding: 8px;
            margin-top: 4px;
            background-color: #222;
            color: white;
            border: 1px solid gold;
        }
        input[type="submit"] {
            background-color: gold;
            color: black;
            margin-top: 20px;
            font-weight: bold;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<h2>Registro de Cliente Online</h2>

<form action="guardar_cliente_online.php" method="post">
    <input type="hidden" name="gimnasio_id" value="<?= htmlspecialchars($gimnasio_id) ?>">

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
        <option value="">Seleccionar...</option>
        <?php foreach ($disciplinas as $disciplina): ?>
            <option value="<?= htmlspecialchars($disciplina['nombre']) ?>">
                <?= htmlspecialchars($disciplina['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="submit" value="Registrar Cliente">
</form>

</body>
</html>
