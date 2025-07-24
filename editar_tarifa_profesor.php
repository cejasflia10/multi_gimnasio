<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profesor_id = intval($_POST['profesor_id']);
    $valor_hora = floatval($_POST['valor_hora']);
    $modo_pago = $_POST['modo_pago'] ?? 'fijo';
    $porcentaje1 = intval($_POST['porcentaje_1'] ?? 50);
    $porcentaje2 = intval($_POST['porcentaje_2'] ?? 75);
    $porcentaje3 = intval($_POST['porcentaje_3'] ?? 100);

    $existe = $conexion->query("SELECT id FROM tarifas_profesor WHERE profesor_id = $profesor_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();

    if ($existe) {
        $sql = "UPDATE tarifas_profesor SET 
            valor_hora = $valor_hora, 
            modo_pago = '$modo_pago', 
            porcentaje_1 = $porcentaje1, 
            porcentaje_2 = $porcentaje2, 
            porcentaje_3 = $porcentaje3";

        if ($modo_pago === 'fijo') {
            $sql .= ", monto_por_hora = $valor_hora";
        }

        $sql .= " WHERE profesor_id = $profesor_id AND gimnasio_id = $gimnasio_id";
        $conexion->query($sql);
    } else {
        $monto = ($modo_pago === 'fijo') ? $valor_hora : 0;
        $conexion->query("INSERT INTO tarifas_profesor (
            profesor_id, valor_hora, monto_por_hora, modo_pago, porcentaje_1, porcentaje_2, porcentaje_3, gimnasio_id
        ) VALUES (
            $profesor_id, $valor_hora, $monto, '$modo_pago', $porcentaje1, $porcentaje2, $porcentaje3, $gimnasio_id
        )");
    }

    $mensaje = "<div style='color: lime; text-align:center;'>✅ Tarifa actualizada correctamente</div>";
}

// Obtener profesores
$profesores = $conexion->query("SELECT id, CONCAT(apellido, ' ', nombre) AS nombre FROM profesores WHERE gimnasio_id = $gimnasio_id ORDER BY apellido");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Tarifa Profesor</title>
    <style>
        body {
            background: #000;
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
            background: #111;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px gold;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            background: #222;
            color: gold;
            border: 1px solid gold;
            border-radius: 4px;
        }
        button {
            margin-top: 15px;
            width: 100%;
            padding: 10px;
            background-color: gold;
            color: black;
            font-weight: bold;
            border: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h2>💰 Editar Tarifa del Profesor</h2>
    <?= $mensaje ?>
    <form method="POST">
        <label for="profesor_id">Profesor:</label>
        <select name="profesor_id" required>
            <option value="">Seleccione un profesor</option>
            <?php while ($row = $profesores->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= $row['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label for="valor_hora">Valor por hora o por asistencia:</label>
        <input type="number" name="valor_hora" step="0.01" required>

        <label for="modo_pago">Modo de pago:</label>
        <select name="modo_pago">
            <option value="fijo">Fijo por hora</option>
            <option value="asistencia">Por asistencia</option>
        </select>

        <label for="porcentaje_1">Porcentaje 1 alumno (por defecto 50):</label>
        <input type="number" name="porcentaje_1" value="50">

        <label for="porcentaje_2">Porcentaje 2 alumnos (por defecto 75):</label>
        <input type="number" name="porcentaje_2" value="75">

        <label for="porcentaje_3">Porcentaje 3+ alumnos (por defecto 100):</label>
        <input type="number" name="porcentaje_3" value="100">

        <button type="submit">Guardar Tarifa</button>
    </form>
</body>
</html>
