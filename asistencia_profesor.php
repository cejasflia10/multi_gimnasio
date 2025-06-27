<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
include 'conexion.php';
include 'menu_horizontal.php';
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}
$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rfid = trim($_POST["rfid"]);
    $fecha = date("Y-m-d");
    $hora_actual = date("H:i:s");

    $stmt = $conexion->prepare("SELECT id FROM profesores WHERE rfid = ?");
    $stmt->bind_param("s", $rfid);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($profesor_id);
        $stmt->fetch();

        $stmt_check = $conexion->prepare("SELECT id, hora_ingreso FROM asistencias_profesores WHERE profesor_id = ? AND fecha = ?");
        $stmt_check->bind_param("is", $profesor_id, $fecha);
        $stmt_check->execute();
        $resultado = $stmt_check->get_result();

        if ($resultado->num_rows > 0) {
            $asistencia = $resultado->fetch_assoc();
            $hora_ingreso = strtotime($asistencia['hora_ingreso']);
            $hora_egreso = strtotime($hora_actual);
            $horas_trabajadas = round(($hora_egreso - $hora_ingreso) / 3600, 2);
            $monto_por_hora = 2000; // modificar según pago por hora
            $monto_pagado = $horas_trabajadas * $monto_por_hora;

            $stmt_update = $conexion->prepare("UPDATE asistencias_profesores SET hora_egreso = ?, horas_trabajadas = ?, monto_pagado = ? WHERE id = ?");
            $stmt_update->bind_param("sdsi", $hora_actual, $horas_trabajadas, $monto_pagado, $asistencia['id']);
            $stmt_update->execute();
            $mensaje = "Egreso registrado. Total horas: $horas_trabajadas.";
        } else {
            $stmt_insert = $conexion->prepare("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("iss", $profesor_id, $fecha, $hora_actual);
            $stmt_insert->execute();
            $mensaje = "Ingreso registrado.";
        }
    } else {
        $mensaje = "RFID no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Asistencia Profesor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
        background-color: #000;
        color: gold;
        font-family: Arial, sans-serif;
        text-align: center;
        padding: 50px;
    }
    input[type="text"] {
        font-size: 20px;
        padding: 10px;
        width: 80%;
        max-width: 400px;
    }
    button {
        padding: 10px 20px;
        font-size: 18px;
        margin-top: 20px;
        background-color: gold;
        border: none;
        cursor: pointer;
    }
    .mensaje {
        margin-top: 30px;
        font-size: 22px;
    }
  </style>
</head>
<body>
  <h1>📛 Asistencia de Profesores</h1>
  <form method="POST">
    <input type="text" name="rfid" placeholder="Escanee tarjeta RFID" autofocus required>
    <br><button type="submit">Registrar</button>
  </form>
  <div class="mensaje"><?= $mensaje ?></div>
</body>
</html>
