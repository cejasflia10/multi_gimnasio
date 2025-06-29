<?php
include 'conexion.php';
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'phpqrcode/qrlib.php';

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
                $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora, fecha_hora, id_gimnasio)
                                  VALUES ($cliente_id, CURDATE(), CURTIME(), NOW(), $gimnasio_id)");
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
            $registro = $conexion->query("SELECT id, hora_ingreso FROM asistencias_profesores 
                                          WHERE profesor_id = $profesor_id AND fecha = CURDATE() 
                                          AND hora_egreso IS NULL")->fetch_assoc();

            if ($registro) {
                $id_asistencia = $registro['id'];
                $hora_ingreso = $registro['hora_ingreso'];
                $hora_egreso = date('H:i:s');

                // Calcular horas trabajadas
                $ingreso_dt = strtotime($hora_ingreso);
                $egreso_dt = strtotime($hora_egreso);
                $horas_trabajadas = round(($egreso_dt - $ingreso_dt) / 3600, 2);

                // Contar alumnos presentes en ese turno (fecha + entre horas)
                $alumnos_q = $conexion->query("SELECT COUNT(*) AS total FROM asistencias
                                              WHERE fecha = CURDATE()
                                              AND hora BETWEEN '$hora_ingreso' AND '$hora_egreso'
                                              AND id_gimnasio = $gimnasio_id");
                $cantidad_alumnos = $alumnos_q->fetch_assoc()['total'] ?? 0;

                // Buscar precio por cantidad de alumnos
                $precio_q = $conexion->query("SELECT precio FROM precio_hora
                                              WHERE gimnasio_id = $gimnasio_id
                                              AND $cantidad_alumnos BETWEEN rango_min AND rango_max
                                              LIMIT 1");
                $precio = $precio_q->fetch_assoc()['precio'] ?? 0;

                $monto_pagado = round($horas_trabajadas * $precio, 2);

                $conexion->query("UPDATE asistencias_profesores SET hora_egreso = NOW(), horas_trabajadas = $horas_trabajadas, monto_pagado = $monto_pagado
                                    WHERE id = $id_asistencia");
                $mensaje = "Egreso registrado para profesor DNI $dni. Monto: $$monto_pagado";
            } else {
                $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso, gimnasio_id)
                                  VALUES ($profesor_id, CURDATE(), NOW(), $gimnasio_id)");
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
