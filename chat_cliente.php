<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red; text-align:center;'>Acceso denegado</div>";
    exit;
}

// Buscar profesor asignado
$profesor = $conexion->query("
    SELECT p.id, p.nombre, p.apellido 
    FROM profesores p 
    JOIN turnos t ON p.id = t.id_profesor 
    JOIN reservas r ON r.turno_id = t.id 
    WHERE r.cliente_id = $cliente_id AND r.fecha >= CURDATE()
    LIMIT 1
")->fetch_assoc();

if (!$profesor) {
    echo "<div style='color:orange; text-align:center;'>No se encontró un profesor asignado.</div>";
    exit;
}

$profesor_id = $profesor['id'];
$nombre_profesor = $profesor['nombre'] . " " . $profesor['apellido'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat con Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h2 class="titulo-seccion">💬 Chat con <?= $nombre_profesor ?></h2>

    <div id="chat-box" class="chat-box"></div>

    <form id="form-mensaje" class="chat-form">
        <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
        <input type="hidden" name="profesor_id" value="<?= $profesor_id ?>">
        <input type="text" name="mensaje" id="mensaje" placeholder="Escribe tu mensaje..." required autocomplete="off">
        <button type="submit">Enviar</button>
    </form>
</div>

<script>
    function cargarMensajes() {
        fetch('ver_mensajes_chat.php?cliente_id=<?= $cliente_id ?>&profesor_id=<?= $profesor_id ?>')
            .then(response => response.text())
            .then(data => {
                const box = document.getElementById('chat-box');
                box.innerHTML = data;
                box.scrollTop = box.scrollHeight;
            });
    }

    document.getElementById('form-mensaje').addEventListener('submit', function (e) {
        e.preventDefault();
        const datos = new FormData(this);
        fetch('guardar_mensaje_chat.php', {
            method: 'POST',
            body: datos
        }).then(() => {
            this.reset();
            cargarMensajes();
        });
    });

    cargarMensajes();
    setInterval(cargarMensajes, 3000);
</script>

</body>
</html>
