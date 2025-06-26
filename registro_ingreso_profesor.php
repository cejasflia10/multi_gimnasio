<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('asistencias')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);
    $fecha = date('Y-m-d');
    $hora_actual = date('H:i:s');

    // Buscar profesor por DNI
    $prof = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE dni = '$dni'")->fetch_assoc();

    if ($prof) {
        $profesor_id = $prof['id'];

        // Verificar si ya tiene una entrada hoy sin salida
        $query = "SELECT * FROM asistencia_profesor 
                  WHERE profesor_id = $profesor_id AND fecha = '$fecha' AND hora_salida IS NULL";
        $registro = $conexion->query($query)->fetch_assoc();

        if ($registro) {
            // Es egreso: calcular horas trabajadas
            $hora_entrada = strtotime($registro['hora_entrada']);
            $hora_salida = strtotime($hora_actual);
            $diferencia = round(($hora_salida - $hora_entrada) / 3600, 2);

            $conexion->query("UPDATE asistencia_profesor 
                              SET hora_salida = '$hora_actual', horas_trabajadas = $diferencia 
                              WHERE id = {$registro['id']}");
            $mensaje = "✅ Egreso registrado para {$prof['apellido']} {$prof['nombre']} | $diferencia hs.";
        } else {
            // Es ingreso
            $conexion->query("INSERT INTO asistencia_profesor (profesor_id, fecha, hora_entrada) 
                              VALUES ($profesor_id, '$fecha', '$hora_actual')");
            $mensaje = "✅ Ingreso registrado para {$prof['apellido']} {$prof['nombre']} a las $hora_actual.";
        }
    } else {
        $mensaje = "❌ Profesor no encontrado con DNI: $dni";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Ingreso/Egreso - Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial;
            text-align: center;
            padding: 40px;
        }
        input[type="text"] {
            padding: 10px;
            font-size: 20px;
            width: 300px;
            margin-bottom: 20px;
            background-color: #111;
            color: gold;
            border: 1px solid #444;
        }
        input[type="submit"] {
            padding: 10px 30px;
            background-color: gold;
            color: black;
            border: none;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
        }
        .mensaje {
            margin-top: 20px;
            font-size: 20px;
            color: #0f0;
        }
    </style>
</head>
<body>

<h1>🕘 Registro Ingreso / Egreso de Profesor</h1>

<form method="POST">
    <input type="text" name="dni" placeholder="Escanear DNI o QR" autofocus required>
    <br>
    <input type="submit" value="Registrar">
</form>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= $mensaje ?></div>
<?php endif; ?>

</body>
</html>
