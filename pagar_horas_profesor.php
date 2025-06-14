<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) {
    die('Acceso no autorizado.');
}

$id_gimnasio = $_SESSION['id_gimnasio'];
$mes = $_GET['mes'] ?? date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profesor_id = $_POST['profesor_id'];
    $monto_hora = floatval($_POST['monto_hora']);
    $horas_totales = floatval($_POST['horas_totales']);
    $total_pagar = $monto_hora * $horas_totales;

    $insert = $conexion->prepare("INSERT INTO pagos_profesor (profesor_id, mes, monto_hora, horas_trabajadas, total_pagado, id_gimnasio) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
    $insert->bind_param("isdddi", $profesor_id, $mes, $monto_hora, $horas_totales, $total_pagar, $id_gimnasio);
    if ($insert->execute()) {
        echo "✅ Pago registrado correctamente.";
    } else {
        echo "❌ Error al registrar el pago: " . $insert->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagar Horas Profesor</title>
    <style>
        body { background-color: #111; color: #fff; font-family: Arial, sans-serif; padding: 30px; }
        label { color: #ffc107; font-weight: bold; }
        input, select { margin: 10px 0; padding: 8px; width: 100%; max-width: 400px; }
        input[type="submit"] {
            background-color: #ffc107;
            color: #000;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h2>💵 Registrar Pago a Profesor</h2>
    <form method="post">
        <label>ID Profesor:</label>
        <input type="number" name="profesor_id" required>

        <label>Mes:</label>
        <input type="month" name="mes" value="<?php echo $mes; ?>" required>

        <label>Total de Horas Trabajadas:</label>
        <input type="number" step="0.01" name="horas_totales" required>

        <label>Monto por Hora:</label>
        <input type="number" step="0.01" name="monto_hora" required>

        <input type="submit" value="Registrar Pago">
    </form>
</body>
</html>
