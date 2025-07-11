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

// PROCESAR ingreso automático
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["codigo"])) {
    $codigo = trim($_POST["codigo"]);

    // Buscar cliente por DNI o RFID
    $stmt = $conexion->prepare("SELECT id, apellido FROM clientes WHERE dni = ? OR rfid = ?");
    $stmt->bind_param("ss", $codigo, $codigo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($cliente = $resultado->fetch_assoc()) {
        $id_cliente = $cliente['id'];

        // Buscar membresía activa
        $stmt2 = $conexion->prepare("SELECT clases_restantes, fecha_vencimiento FROM membresias WHERE cliente_id = ? ORDER BY fecha_vencimiento DESC LIMIT 1");
        $stmt2->bind_param("i", $id_cliente);
        $stmt2->execute();
        $resultado2 = $stmt2->get_result();

        if ($membresia = $resultado2->fetch_assoc()) {
            $clases = (int)$membresia['clases_restantes'];
            $vencimiento = $membresia['fecha_vencimiento'];

            if ($clases > 0 && $vencimiento >= $hoy) {
                // Registrar asistencia con id_gimnasio
                $stmt3 = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha_hora, id_gimnasio) VALUES (?, NOW(), ?)");
                $stmt3->bind_param("ii", $id_cliente, $gimnasio_id);
                $stmt3->execute();

                // Descontar clase
                $conexion->query("UPDATE membresias SET clases_restantes = clases_restantes - 1 WHERE cliente_id = $id_cliente AND fecha_vencimiento = '$vencimiento'");
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
    SELECT c.apellido, m.clases_restantes, m.fecha_vencimiento
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    JOIN membresias m ON m.cliente_id = c.id
    WHERE DATE(a.fecha_hora) = '$hoy' AND a.id_gimnasio = $gimnasio_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Asistencia</title>
    <link rel="stylesheet" href="estilo_unificado.css">

</head>
<body>
    <div class="contenedor">
    <div class="logo">
        <img src="logo.png" alt="Logo del Gimnasio" height="60">
    </div>

    <div class="input-container">
        <form method="post" action="registrar_asistencia.php" id="formIngreso">
            <input type="text" name="codigo" autofocus autocomplete="off" placeholder="">
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
                    <td><?= $row['clases_restantes'] ?></td>
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
