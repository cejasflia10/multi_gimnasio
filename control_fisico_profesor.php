<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? null;
if (!$profesor_id) {
    die("Acceso denegado.");
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = intval($_POST['cliente_id']);
    $fecha = $_POST['fecha'];
    $peso = floatval($_POST['peso']);
    $altura = floatval($_POST['altura']);
    $edad = intval($_POST['edad']);
    $nivel = $_POST['nivel'];
    $objetivo = $_POST['objetivo'];
    $observaciones = $_POST['observaciones'];
    $tipo = $_POST['tipo_control'];

    $imc = $altura > 0 ? round($peso / pow($altura / 100, 2), 2) : 0;

    $conexion->query("INSERT INTO controles_fisicos 
        (cliente_id, profesor_id, fecha, peso, altura, edad, imc, nivel, objetivo, observaciones, tipo_control) 
        VALUES ($cliente_id, $profesor_id, '$fecha', $peso, $altura, $edad, $imc, 
        '$nivel', '$objetivo', '$observaciones', '$tipo')");
    
    echo "<script>alert('Ficha física registrada'); window.location.href='control_fisico_profesor.php';</script>";
    exit;
}

// Obtener alumnos del profesor
$alumnos_q = $conexion->query("
    SELECT DISTINCT c.id, c.nombre, c.apellido 
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.profesor_id = $profesor_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control Físico del Alumno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        h2, h3 {
            text-align: center;
        }

        form {
            max-width: 600px;
            margin: auto;
            background: #222;
            padding: 20px;
            border-radius: 10px;
        }

        label {
            display: block;
            margin-top: 10px;
        }

        input, select, textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            background: #000;
            color: gold;
            border: 1px solid gold;
            border-radius: 5px;
        }

        button {
            margin-top: 20px;
            background-color: gold;
            color: black;
            font-weight: bold;
            padding: 10px;
            border: none;
            width: 100%;
            border-radius: 5px;
            cursor: pointer;
        }

        .card {
            background: #222;
            margin: 20px auto;
            padding: 15px;
            border-radius: 8px;
            max-width: 700px;
        }

        .card h4 {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h2>📋 Registrar Ficha Física</h2>

    <form method="POST">
        <label>Alumno:</label>
        <select name="cliente_id" required>
            <option value="">Seleccionar alumno</option>
            <?php while ($alumno = $alumnos_q->fetch_assoc()): ?>
                <option value="<?= $alumno['id'] ?>">
                    <?= $alumno['apellido'] . ' ' . $alumno['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Fecha del control:</label>
        <input type="date" name="fecha" required>

        <label>Peso (kg):</label>
        <input type="number" step="0.1" name="peso" required>

        <label>Altura (cm):</label>
        <input type="number" step="0.1" name="altura" required>

        <label>Edad:</label>
        <input type="number" name="edad" required>

        <label>Nivel:</label>
        <input type="text" name="nivel">

        <label>Objetivo:</label>
        <input type="text" name="objetivo">

        <label>Observaciones:</label>
        <textarea name="observaciones"></textarea>

        <label>Tipo de control:</label>
        <select name="tipo_control">
            <option value="semanal">Semanal</option>
            <option value="mensual">Mensual</option>
        </select>

        <button type="submit">Guardar Ficha Física</button>
    </form>

    <div class="card">
        <h3>📜 Controles Recientes</h3>
        <?php
        $controles_q = $conexion->query("
            SELECT f.*, c.nombre, c.apellido 
            FROM controles_fisicos f
            JOIN clientes c ON f.cliente_id = c.id
            WHERE f.profesor_id = $profesor_id
            ORDER BY f.fecha DESC LIMIT 10
        ");
        while ($f = $controles_q->fetch_assoc()):
        ?>
            <div style="margin-bottom: 10px;">
                <h4><?= $f['apellido'] . ', ' . $f['nombre'] ?> (<?= $f['tipo_control'] ?>)</h4>
                <small><?= $f['fecha'] ?> | IMC: <?= $f['imc'] ?></small><br>
                Peso: <?= $f['peso'] ?> kg | Altura: <?= $f['altura'] ?> cm | Edad: <?= $f['edad'] ?><br>
                Objetivo: <?= $f['objetivo'] ?><br>
                Observaciones: <?= $f['observaciones'] ?>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>
