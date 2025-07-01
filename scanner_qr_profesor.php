<?php
session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['qr'])) {
    $dni = trim($_POST['qr']);
    $fecha_hoy = date("Y-m-d");
    $hora_actual = date("H:i:s");

    // Buscar cliente
    $q = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'");
    if ($q->num_rows == 0) {
        $mensaje = "Cliente no encontrado.";
    } else {
        $cliente_id = $q->fetch_assoc()['id'];

        // Buscar membresía activa con clases disponibles
        $membresia = $conexion->query("
            SELECT id, clases_disponibles
            FROM membresias
            WHERE cliente_id = $cliente_id
              AND fecha_vencimiento >= '$fecha_hoy'
              AND clases_disponibles > 0
              ORDER BY fecha_inicio DESC
              LIMIT 1
        ");

        if ($membresia->num_rows == 0) {
            $mensaje = "No tiene clases disponibles o su membresía está vencida.";
        } else {
            $mem = $membresia->fetch_assoc();
            $membresia_id = $mem['id'];
            $clases = $mem['clases_disponibles'];

            // Registrar asistencia del cliente
            $conexion->query("
                INSERT INTO asistencias_clientes (cliente_id, fecha, hora, profesor_id)
                VALUES ($cliente_id, '$fecha_hoy', '$hora_actual', $profesor_id)
            ");

            // Descontar una clase
            $conexion->query("
                UPDATE membresias SET clases_disponibles = clases_disponibles - 1
                WHERE id = $membresia_id
            ");

            // Registrar asistencia del profesor si aún no fue registrada hoy
            $ya = $conexion->query("
                SELECT id FROM asistencias_profesor
                WHERE profesor_id = $profesor_id AND fecha = '$fecha_hoy'
            ");
            if ($ya->num_rows == 0) {
                $conexion->query("
                    INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso)
                    VALUES ($profesor_id, '$fecha_hoy', '$hora_actual')
                ");
            }

            $mensaje = "Ingreso registrado correctamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Escáner Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background-color: #000; color: gold; font-family: Arial; text-align: center; padding: 20px; }
        input[type="text"] { padding: 10px; width: 80%; font-size: 18px; }
        button { padding: 10px 20px; margin-top: 10px; font-weight: bold; }
        .mensaje { margin-top: 20px; font-size: 20px; }
    </style>
</head>
<body>

    <h2>Escanear QR del Alumno</h2>
    <form method="POST">
        <input type="text" name="qr" placeholder="Escanear DNI del alumno" autofocus>
        <br>
        <button type="submit">Registrar</button>
    </form>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>

</body>
</html>
