<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = date('Y-m-d');
    $desayuno = $_POST['desayuno'] ?? '';
    $almuerzo = $_POST['almuerzo'] ?? '';
    $merienda = $_POST['merienda'] ?? '';
    $cena = $_POST['cena'] ?? '';
    $gramos_desayuno = intval($_POST['gramos_desayuno'] ?? 0);
    $gramos_almuerzo = intval($_POST['gramos_almuerzo'] ?? 0);
    $gramos_merienda = intval($_POST['gramos_merienda'] ?? 0);
    $gramos_cena = intval($_POST['gramos_cena'] ?? 0);

    $conexion->query("
        INSERT INTO comidas_cliente (cliente_id, gimnasio_id, fecha,
            desayuno, gramos_desayuno,
            almuerzo, gramos_almuerzo,
            merienda, gramos_merienda,
            cena, gramos_cena
        ) VALUES (
            $cliente_id, $gimnasio_id, '$fecha',
            '$desayuno', $gramos_desayuno,
            '$almuerzo', $gramos_almuerzo,
            '$merienda', $gramos_merienda,
            '$cena', $gramos_cena
        )
    ");

    $mensaje = "✅ Comidas registradas correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Diario de Comidas</title>
    <style>
        body {
            background: black;
            color: gold;
            font-family: Arial;
            padding: 20px;
        }
        input, textarea {
            width: 100%;
            padding: 8px;
            margin: 5px 0 10px;
            border-radius: 5px;
        }
        button {
            padding: 10px 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<h2>🍽️ Registrar Comidas del Día</h2>

<?php if (!empty($mensaje)) echo "<p style='color:lime;'>$mensaje</p>"; ?>

<form method="POST">
    <label>Desayuno:</label>
    <textarea name="desayuno" required></textarea>
    <label>Cantidad (gr):</label>
    <input type="number" name="gramos_desayuno" required>

    <label>Almuerzo:</label>
    <textarea name="almuerzo" required></textarea>
    <label>Cantidad (gr):</label>
    <input type="number" name="gramos_almuerzo" required>

    <label>Merienda:</label>
    <textarea name="merienda" required></textarea>
    <label>Cantidad (gr):</label>
    <input type="number" name="gramos_merienda" required>

    <label>Cena:</label>
    <textarea name="cena" required></textarea>
    <label>Cantidad (gr):</label>
    <input type="number" name="gramos_cena" required>

    <button type="submit">Guardar</button>
</form>

</body>
</html>
