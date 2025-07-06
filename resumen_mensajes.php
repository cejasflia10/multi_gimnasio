<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

function tiempo_relativo($fecha) {
    $hace = time() - strtotime($fecha);
    if ($hace < 60) return 'hace ' . $hace . 's';
    elseif ($hace < 3600) return 'hace ' . floor($hace / 60) . ' min';
    elseif ($hace < 86400) return 'hace ' . floor($hace / 3600) . ' h';
    else return date('d/m H:i', strtotime($fecha));
}

echo "<div class='contenedor-mensajes'>";
echo "<h4>📩 Últimos mensajes:</h4>";

if (isset($_SESSION['cliente_id'])) {
    $cliente_id = $_SESSION['cliente_id'];

    $res = $conexion->query("
        SELECT mc.mensaje, mc.fecha, p.nombre, p.apellido, p.id AS profesor_id
        FROM mensajes_chat mc
        JOIN profesores p ON mc.profesor_id = p.id
        WHERE mc.cliente_id = $cliente_id AND mc.emisor = 'profesor'
        ORDER BY mc.fecha DESC
        LIMIT 5
    ");

    if ($res->num_rows == 0) {
        echo "<p>No hay mensajes recientes.</p>";
    } else {
        while ($m = $res->fetch_assoc()) {
            $nombre = $m['nombre'] . ' ' . $m['apellido'];
            $msg = substr($m['mensaje'], 0, 40);
            $tiempo = tiempo_relativo($m['fecha']);
            echo "<p><a href='chat_cliente.php' style='color:gold; text-decoration:none;'>
                <strong>$nombre</strong>: \"$msg\" 
                <span style='color:gray'>($tiempo)</span>
            </a></p>";
        }
    }

} elseif (isset($_SESSION['profesor_id'])) {
    $profesor_id = $_SESSION['profesor_id'];

    $res = $conexion->query("
        SELECT mc.mensaje, mc.fecha, c.nombre, c.apellido, c.id AS cliente_id
        FROM mensajes_chat mc
        JOIN clientes c ON mc.cliente_id = c.id
        WHERE mc.profesor_id = $profesor_id AND mc.emisor = 'cliente'
        ORDER BY mc.fecha DESC
        LIMIT 5
    ");

    if ($res->num_rows == 0) {
        echo "<p>No hay mensajes recientes.</p>";
    } else {
        while ($m = $res->fetch_assoc()) {
            $nombre = $m['nombre'] . ' ' . $m['apellido'];
            $msg = substr($m['mensaje'], 0, 40);
            $tiempo = tiempo_relativo($m['fecha']);
            echo "<p>
                <a href='chat_profesor.php?cliente_id={$m['cliente_id']}' style='color:gold; text-decoration:none;'>
                    <strong>$nombre</strong>: \"$msg\" 
                    <span style='color:gray'>($tiempo)</span>
                </a>
            </p>";
        }
    }
}

echo "</div>";
