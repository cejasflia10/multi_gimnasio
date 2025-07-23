<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = date('Y-m-d');
$hora_actual = date('H:i:s');
$advertencia = "";
$activar_sonido = false;

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Datos del gimnasio
$info = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$nombre_gimnasio = $info['nombre'] ?? 'Gimnasio';
$logo_gimnasio = $info['logo'] ?? 'logo.png';

// Procesar código
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["codigo"])) {
    $codigo = trim($_POST["codigo"]);

    // --- Verificar si es profesor ---
    $prof_stmt = $conexion->prepare("SELECT id, apellido, nombre FROM profesores WHERE dni = ? AND gimnasio_id = ?");
    $prof_stmt->bind_param("si", $codigo, $gimnasio_id);
    $prof_stmt->execute();
    $prof_result = $prof_stmt->get_result();

    if ($prof_result->num_rows > 0) {
        $prof = $prof_result->fetch_assoc();
        $prof_id = $prof['id'];
        $nombre_prof = $prof['apellido'] . ' ' . $prof['nombre'];

        $check_asistencia = $conexion->query("
            SELECT id, hora_entrada, hora_salida 
            FROM asistencias_profesores 
            WHERE profesor_id = $prof_id AND fecha = '$hoy' AND gimnasio_id = $gimnasio_id 
            ORDER BY id DESC 
            LIMIT 1
        ");

        $hora_actual = date('H:i:s');

        if ($check_asistencia && $check_asistencia->num_rows > 0) {
            $registro = $check_asistencia->fetch_assoc();
            if (empty($registro['hora_salida'])) {
                $conexion->query("UPDATE asistencias_profesores SET hora_salida = '$hora_actual' WHERE id = {$registro['id']}");
                $advertencia = "✅ Salida registrada para $nombre_prof a las $hora_actual.";
            } else {
                $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_entrada, gimnasio_id, hora) VALUES ($prof_id, '$hoy', '$hora_actual', $gimnasio_id, '$hora_actual')");
                $advertencia = "✅ Nuevo ingreso registrado para $nombre_prof a las $hora_actual.";
            }
        } else {
            $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_entrada, gimnasio_id, hora) VALUES ($prof_id, '$hoy', '$hora_actual', $gimnasio_id, '$hora_actual')");
            $advertencia = "✅ Ingreso registrado para $nombre_prof a las $hora_actual.";
        }

    } else {
        // --- Si no es profesor, es cliente ---
        $stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
        $stmt->bind_param("si", $codigo, $gimnasio_id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($cliente = $resultado->fetch_assoc()) {
            $id_cliente = $cliente['id'];

            $stmt2 = $conexion->prepare("SELECT clases_disponibles, fecha_vencimiento FROM membresias WHERE cliente_id = ? AND gimnasio_id = ? ORDER BY fecha_vencimiento DESC LIMIT 1");
            $stmt2->bind_param("ii", $id_cliente, $gimnasio_id);
            $stmt2->execute();
            $resultado2 = $stmt2->get_result();

            if ($membresia = $resultado2->fetch_assoc()) {
                $clases = (int)$membresia['clases_disponibles'];
                $vencimiento = $membresia['fecha_vencimiento'];

                if ($clases > 0 && $vencimiento >= $hoy) {
                    $hora_actual = date('H:i:s');
                    $query = "INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id) VALUES ($id_cliente, '$hoy', '$hora_actual', $gimnasio_id)";
                    if (!$conexion->query($query)) {
                        die("❌ Error al registrar asistencia: " . $conexion->error);
                    }

                    $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE cliente_id = $id_cliente AND fecha_vencimiento = '$vencimiento' AND gimnasio_id = $gimnasio_id");

                    $advertencia = "✅ Asistencia registrada para cliente a las $hora_actual.";
                } else {
                    $advertencia = "❌ ¡Membresía vencida o sin clases disponibles!";
                    $activar_sonido = true;
                }
            } else {
                $advertencia = "❌ ¡El cliente no tiene membresía registrada!";
                $activar_sonido = true;
            }
        } else {
            $advertencia = "❌ ¡Cliente no encontrado!";
            $activar_sonido = true;
        }
    }
}

// --- Profesores que asistieron hoy ---
$profesores = $conexion->query("
    SELECT p.apellido, ap.hora_entrada, ap.hora_salida
    FROM asistencias_profesores ap
    INNER JOIN profesores p ON ap.profesor_id = p.id
    WHERE ap.fecha = '$hoy' AND ap.gimnasio_id = $gimnasio_id
");

// --- Clientes que asistieron hoy ---
$clientes = $conexion->query("
    SELECT c.apellido, m.clases_disponibles, m.fecha_vencimiento, a.hora
    FROM asistencias a
    INNER JOIN clientes c ON a.cliente_id = c.id
    LEFT JOIN membresias m ON m.cliente_id = c.id
        AND m.gimnasio_id = $gimnasio_id
        AND m.fecha_vencimiento = (
            SELECT MAX(fecha_vencimiento)
            FROM membresias
            WHERE cliente_id = c.id AND gimnasio_id = $gimnasio_id
        )
    WHERE a.fecha = '$hoy' AND a.gimnasio_id = $gimnasio_id
    ORDER BY a.hora DESC
");
?>

<!-- HTML permanece igual -->

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Asistencia</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background-color: #111; color: gold; }
        .contenedor { padding: 20px; }
        .encabezado {
            display: flex; justify-content: space-between; align-items: center;
        }
        .encabezado h1 { font-size: 28px; margin: 0; }
        input[type="text"] {
            font-size: 20px; padding: 10px; width: 100%; margin: 10px 0;
        }
        table {
            width: 100%; border-collapse: collapse; margin-top: 10px;
        }
        table th, table td {
            border: 1px solid #444; padding: 8px; text-align: center;
        }
        .advertencia {
            font-size: 18px; margin: 15px 0;
            color: <?= $activar_sonido ? 'red' : 'lime' ?>;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <div class="encabezado">
            <img src="<?= $logo_gimnasio ?>" height="70">
            <h1><?= strtoupper($nombre_gimnasio) ?></h1>
        </div>

        <form method="POST" action="">
            <input type="text" name="codigo" autofocus placeholder="Ingresar DNI...">
        </form>

        <?php if ($advertencia): ?>
            <div class="advertencia"><?= $advertencia ?></div>
        <?php endif; ?>

        <?php if ($activar_sonido): ?>
            <audio autoplay><source src="alerta.mp3" type="audio/mpeg"></audio>
        <?php endif; ?>

        <h2>👨‍🏫 Profesores Hoy</h2>
        <table>
            <tr><th>Apellido</th><th>Ingreso</th><th>Salida</th></tr>
            <?php while ($row = $profesores->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['hora_entrada'] ?: '-' ?></td>
                <td><?= $row['hora_salida'] ?: '-' ?></td>
            </tr>
            <?php endwhile; ?>
        </table>

        <h2>🏋️ Clientes Hoy</h2>
        <table>
            <tr><th>Apellido</th><th>Hora</th><th>Clases</th><th>Vencimiento</th></tr>
            <?php while ($row = $clientes->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['hora'] ?></td>
                <td><?= $row['clases_disponibles'] ?? 'N/D' ?></td>
                <td><?= $row['fecha_vencimiento'] ?? 'N/D' ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
