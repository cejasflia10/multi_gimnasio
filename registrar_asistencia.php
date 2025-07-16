<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = date('Y-m-d');

$advertencia = "";
$activar_sonido = false;

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener nombre y logo del gimnasio
$info = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$nombre_gimnasio = $info['nombre'] ?? 'Gimnasio';
$logo_gimnasio = $info['logo'] ?? 'logo.png';

// PROCESAR ingreso automático
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["codigo"])) {
    $codigo = trim($_POST["codigo"]);

    // Buscar cliente por DNI
    $stmt = $conexion->prepare("SELECT id, apellido FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $codigo, $gimnasio_id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($cliente = $resultado->fetch_assoc()) {
        $id_cliente = $cliente['id'];

        // Buscar membresía activa del gimnasio correspondiente
        $stmt2 = $conexion->prepare("SELECT clases_disponibles, fecha_vencimiento FROM membresias WHERE cliente_id = ? AND gimnasio_id = ? ORDER BY fecha_vencimiento DESC LIMIT 1");
        $stmt2->bind_param("ii", $id_cliente, $gimnasio_id);
        $stmt2->execute();
        $resultado2 = $stmt2->get_result();

        if ($membresia = $resultado2->fetch_assoc()) {
            $clases = (int)$membresia['clases_disponibles'];
            $vencimiento = $membresia['fecha_vencimiento'];

            if ($clases > 0 && $vencimiento >= $hoy) {
                // Registrar asistencia
                $stmt3 = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha_hora, id_gimnasio) VALUES (?, NOW(), ?)");
                $stmt3->bind_param("ii", $id_cliente, $gimnasio_id);
                $stmt3->execute();

                // Descontar clase
                $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE cliente_id = $id_cliente AND fecha_vencimiento = '$vencimiento' AND gimnasio_id = $gimnasio_id");
            } else {
                $advertencia = "¡Membresía vencida o sin clases disponibles!";
                $activar_sonido = true;
            }
        } else {
            $advertencia = "¡El cliente no tiene membresía registrada!";
            $activar_sonido = true;
        }
    } else {
        $advertencia = "¡Cliente no encontrado!";
        $activar_sonido = true;
    }
}

// CONSULTAS para mostrar los registros del día
$profesores = $conexion->query("
    SELECT p.apellido, ap.hora_entrada, ap.hora_salida
    FROM asistencias_profesor ap
    JOIN profesores p ON ap.profesor_id = p.id
    WHERE ap.fecha = '$hoy' AND ap.gimnasio_id = $gimnasio_id
");

$clientes = $conexion->query("
    SELECT c.apellido, m.clases_disponibles, m.fecha_vencimiento
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    JOIN membresias m ON m.cliente_id = c.id AND m.gimnasio_id = $gimnasio_id
    WHERE DATE(a.fecha_hora) = '$hoy' AND a.id_gimnasio = $gimnasio_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Asistencia</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
        }
        .encabezado h1 {
            color: gold;
            font-size: 30px;
            margin: 0;
        }
        .input-container input[type="text"] {
            font-size: 20px;
            padding: 10px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="contenedor">

        <!-- Encabezado -->
        <div class="encabezado">
            <img src="<?= $logo_gimnasio ?>" alt="Logo" height="80">
            <h1><?= strtoupper($nombre_gimnasio) ?></h1>
        </div>

        <div class="input-container">
            <form method="post" action="registrar_asistencia.php" id="formIngreso">
                <input type="text" name="codigo" autofocus autocomplete="off" placeholder="Ingrese DNI">
                <input type="submit" value="Registrar" style="display:none;">
            </form>
        </div>

        <?php if ($advertencia): ?>
        <div class="advertencia" id="advertencia"><?= $advertencia ?></div>
        <?php endif; ?>

        <audio id="alertaSonido" src="alerta.mp3" preload="auto"></audio>

        <div class="secciones">
            <div class="tabla-box">
                <h2>Profesores Hoy</h2>
                <table>
                    <tr><th>Apellido</th><th>Ingreso</th><th>Salida</th></tr>
                    <?php while ($row = $profesores->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['apellido'] ?></td>
                        <td><?= $row['hora_entrada'] ?></td>
                        <td><?= $row['hora_salida'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>

            <div class="tabla-box">
                <h2>Clientes Hoy</h2>
                <table>
                    <tr><th>Apellido</th><th>Clases</th><th>Vencimiento</th></tr>
                    <?php while ($row = $clientes->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['apellido'] ?></td>
                        <td><?= $row['clases_disponibles'] ?></td>
                        <td><?= $row['fecha_vencimiento'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>

        <?php if ($activar_sonido): ?>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                document.getElementById("alertaSonido").play();
            });
        </script>
        <?php endif; ?>
    </div>
</body>
</html>
