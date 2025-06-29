<?php
include 'conexion.php';
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qr = trim($_POST['qr'] ?? '');
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    if (strpos($qr, 'C-') === 0) {
        $dni = substr($qr, 2);
        $cliente = $conexion->query("SELECT id, clases_disponibles, fecha_vencimiento FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id")->fetch_assoc();

        if ($cliente) {
            $cliente_id = $cliente['id'];
            $fecha_vencimiento = $cliente['fecha_vencimiento'];
            $clases = $cliente['clases_disponibles'];

            if ($fecha_vencimiento >= date('Y-m-d') && $clases > 0) {
                $conexion->query("INSERT INTO asistencias (persona_id, tipo, fecha, hora_ingreso, gimnasio_id)
                                  VALUES ($cliente_id, 'cliente', CURDATE(), NOW(), $gimnasio_id)");
                $conexion->query("UPDATE clientes SET clases_disponibles = clases_disponibles - 1 WHERE id = $cliente_id");
                $mensaje = "Ingreso registrado para cliente DNI $dni";
            } else {
                $mensaje = "Cliente sin clases disponibles o membresía vencida.";
            }
        } else {
            $mensaje = "Cliente no encontrado.";
        }

    } elseif (strpos($qr, 'P-') === 0) {
        $dni = substr($qr, 2);
        $profesor = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id")->fetch_assoc();

        if ($profesor) {
            $profesor_id = $profesor['id'];
            $registro = $conexion->query("SELECT id, hora_ingreso FROM asistencias 
                                          WHERE persona_id = $profesor_id AND tipo = 'profesor' AND fecha = CURDATE() 
                                          AND hora_egreso IS NULL")->fetch_assoc();

            if ($registro) {
                $conexion->query("UPDATE asistencias SET hora_egreso = NOW() WHERE id = {$registro['id']}");
                $mensaje = "Egreso registrado para profesor DNI $dni";
            } else {
                $conexion->query("INSERT INTO asistencias (persona_id, tipo, fecha, hora_ingreso, gimnasio_id)
                                  VALUES ($profesor_id, 'profesor', CURDATE(), NOW(), $gimnasio_id)");
                $mensaje = "Ingreso registrado para profesor DNI $dni";
            }
        } else {
            $mensaje = "Profesor no encontrado.";
        }
    } else {
        $mensaje = "QR inválido";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR MultiIngreso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 40px;
        }
        input[type="text"] {
            padding: 10px;
            font-size: 20px;
            width: 80%;
            max-width: 400px;
            margin-bottom: 20px;
        }
        input[type="submit"] {
            background: gold;
            border: none;
            padding: 10px 20px;
            font-size: 18px;
            cursor: pointer;
        }
        .mensaje {
            margin-top: 30px;
            font-size: 20px;
            color: #0f0;
        }
    </style>
</head>
<body>
    <h1>Registro por QR</h1>
    <form method="POST">
        <input type="text" name="qr" placeholder="Escaneá el QR aquí o ingresá el código" autofocus required>
        <br>
        <input type="submit" value="Registrar">
    </form>
    <?php if ($mensaje): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
</body>
</html>
