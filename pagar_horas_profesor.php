<?php
session_start();
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}
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
    <link rel="stylesheet" href="estilo_unificado.css">

</head>
<body>
    <div class="contenedor">
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
</div>
</body>
</html>
