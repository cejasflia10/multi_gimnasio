<?php
include 'conexion.php';
session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);

    // Buscar profesor por DNI
    $stmt = $conexion->prepare("SELECT * FROM profesores WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $profesor = $resultado->fetch_assoc();

    if (!$profesor) {
        $mensaje = "Profesor no encontrado.";
    } else {
        $profesor_id = $profesor['id'];
        $fecha = date('Y-m-d');
        $hora_actual = date('H:i:s');

        // Verificar si ya tiene ingreso hoy sin egreso
        $consulta = $conexion->query("SELECT * FROM asistencias_profesor 
            WHERE profesor_id = $profesor_id AND fecha = '$fecha' 
            AND hora_egreso IS NULL");

        if ($consulta->num_rows > 0) {
            // Registrar hora de salida
            $registro = $consulta->fetch_assoc();
            $hora_ingreso = new DateTime($registro['hora_ingreso']);
            $hora_egreso = new DateTime($hora_actual);
            $intervalo = $hora_ingreso->diff($hora_egreso);
            $horas = $intervalo->h + ($intervalo->i / 60);

            // Obtener cantidad de alumnos en el horario
            $alumnos_q = $conexion->query("
                SELECT COUNT(*) as cantidad 
                FROM asistencias_clientes 
                WHERE fecha = '$fecha' 
                  AND hora BETWEEN '{$registro['hora_ingreso']}' AND '$hora_actual'
            ");
            $alumnos = $alumnos_q->fetch_assoc()['cantidad'];

            // Calcular monto a pagar según alumnos
            if ($alumnos >= 10) {
                $monto = 2000 * $horas;
            } elseif ($alumnos >= 4) {
                $monto = 1500 * $horas;
            } elseif ($alumnos >= 1) {
                $monto = 1000 * $horas;
            } else {
                $monto = 0;
            }

            $conexion->query("UPDATE asistencias_profesor 
                SET hora_egreso = '$hora_actual', 
                    alumnos_presentes = $alumnos,
                    monto_a_pagar = $monto 
                WHERE id = {$registro['id']}");

            $mensaje = "Salida registrada. Total horas: " . round($horas, 2) . ", alumnos: $alumnos, monto: $$monto.";
        } else {
            // Verificar si hay al menos 1 alumno registrado en la última hora
            $alumnos_q = $conexion->query("
                SELECT COUNT(*) as cantidad 
                FROM asistencias_clientes 
                WHERE fecha = '$fecha' 
                  AND hora >= SUBTIME('$hora_actual', '01:00:00')
            ");
            $alumnos = $alumnos_q->fetch_assoc()['cantidad'];

            if ($alumnos == 0) {
                $mensaje = "No hay alumnos registrados en este horario. No se puede ingresar.";
            } else {
                $conexion->query("INSERT INTO asistencias_profesor 
                    (profesor_id, fecha, hora_ingreso) 
                    VALUES ($profesor_id, '$fecha', '$hora_actual')");
                $mensaje = "Ingreso registrado correctamente.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Asistencia Profesor</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 30px;
        }
        input {
            padding: 10px;
            font-size: 18px;
            width: 60%;
            margin-bottom: 10px;
        }
        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Registro de Asistencia - Profesor</h1>
    <form method="POST">
        <input type="text" name="dni" placeholder="Escanear QR o ingresar DNI" required autofocus><br>
        <button type="submit">Registrar</button>
    </form>

    <?php if ($mensaje): ?>
        <p><strong><?= $mensaje ?></strong></p>
    <?php endif; ?>
</body>
</html>
