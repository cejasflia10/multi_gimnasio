<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
$rol = $_SESSION['rol'] ?? null;

if (!$gimnasio_id && $rol != 'admin') {
    die("Acceso denegado.");
}

// Cargar disciplinas
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas");

// Cargar gimnasios solo si es administrador
if ($rol == 'admin') {
    $gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #ffd700;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        form {
            max-width: 600px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 8px;
        }
        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 4px;
            margin-bottom: 10px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
        }
        input[type="submit"] {
            background-color: #ffd700;
            color: #111;
            font-weight: bold;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #e5c100;
        }
        @media screen and (max-width: 480px) {
            body {
                padding: 10px;
            }
            form {
                padding: 15px;
            }
            input, select {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<h2>Agregar Cliente</h2>

<form action="guardar_cliente.php" method="POST">
    <label for="apellido">Apellido:</label>
    <input type="text" name="apellido" required>

    <label for="nombre">Nombre:</label>
    <input type="text" name="nombre" required>

    <label for="dni">DNI:</label>
    <input type="text" name="dni" required>

    <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
    <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required onchange="calcularEdad()">

    <label for="edad">Edad:</label>
    <input type="text" name="edad" id="edad" readonly>

    <label for="domicilio">Domicilio:</label>
    <input type="text" name="domicilio">

    <label for="telefono">Teléfono:</label>
    <input type="text" name="telefono">

    <label for="email">Email:</label>
    <input type="email" name="email">

    <label for="disciplina">Disciplina:</label>
    <select name="disciplina">
        <option value="">Seleccionar</option>
        <?php while ($d = $disciplinas->fetch_assoc()): ?>
            <option value="<?= $d['nombre'] ?>"><?= $d['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <?php if ($rol == 'admin'): ?>
        <label for="gimnasio_id">Gimnasio:</label>
        <select name="gimnasio_id" required>
            <option value="">Seleccionar</option>
            <?php while ($g = $gimnasios->fetch_assoc()): ?>
                <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
            <?php endwhile; ?>
        </select>
    <?php else: ?>
        <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">
    <?php endif; ?>

    <input type="submit" value="Guardar Cliente">
</form>

<script>
    function calcularEdad() {
        const fechaNac = document.getElementById('fecha_nacimiento').value;
        if (!fechaNac) return;

        const hoy = new Date();
        const nacimiento = new Date(fechaNac);
        let edad = hoy.getFullYear() - nacimiento.getFullYear();
        const mes = hoy.getMonth() - nacimiento.getMonth();

        if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
            edad--;
        }

        document.getElementById('edad').value = edad;
    }
</script>

</body>
</html>
