<?php
// Iniciar la sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

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

            $conexion->query("UPDATE asistencias_profesor 
                SET hora_egreso = '$hora_actual'
                WHERE id = {$registro['id']}");

            $mensaje = "Salida registrada. Total horas: " . round($horas, 2);
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

// Registrar asistencia del profesor automáticamente al escanear un cliente
if (isset($_SESSION['profesor_id'])) {
    $profesor_id = $_SESSION['profesor_id'];
    $gimnasio_id = $_SESSION['gimnasio_id'];
    $fecha_hoy = date("Y-m-d");
    $hora_actual = date("H:i:s");

    // Verificar si hay una asistencia activa sin salida
    $verif = $conexion->prepare("SELECT id FROM asistencias_profesores WHERE profesor_id = ? AND fecha = ? AND hora_salida IS NULL");
    $verif->bind_param("is", $profesor_id, $fecha_hoy);
    $verif->execute();
    $res = $verif->get_result();

    if ($res->num_rows > 0) {
        // Registrar salida
        $asistencia = $res->fetch_assoc();
        $asistencia_id = $asistencia['id'];
        $conexion->query("UPDATE asistencias_profesores SET hora_salida = '$hora_actual' WHERE id = $asistencia_id");
    } else {
        // Registrar ingreso
        $conexion->query("INSERT INTO asistencias_profesores (profesor_id, gimnasio_id, fecha, hora_ingreso) VALUES ($profesor_id, $gimnasio_id, '$fecha_hoy', '$hora_actual')");
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="estilo_unificado.css">
    <meta charset="UTF-8">
    <title>Asistencia Profesor</title>
</head>
<body>
    <div class="contenedor">
        <h1>Registro de Asistencia - Profesor</h1>
        <form method="POST">
            <input type="text" name="dni" placeholder="Escanear QR o ingresar DNI" required autofocus><br>
            <button type="submit">Registrar</button>
        </form>

        <?php if ($mensaje): ?>
            <p><strong><?= $mensaje ?></strong></p>
        <?php endif; ?>
    </div>
</body>
</html>
