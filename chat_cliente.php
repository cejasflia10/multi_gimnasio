<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red;'>Acceso denegado.</div>";
    exit;
}

// Obtener todos los profesores del gimnasio
$profesores = $conexion->query("
    SELECT id, nombre, apellido 
    FROM profesores 
    WHERE gimnasio_id = $gimnasio_id
");

$profesor_id = intval($_GET['profesor_id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat con profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">💬 Chat con profesor</h2>

    <form method="GET" class="formulario">
        <label for="profesor_id">Seleccionar profesor:</label>
        <select name="profesor_id" id="profesor_id" onchange="this.form.submit()">
            <option value="">-- Elegí profesor --</option>
            <?php while ($p = $profesores->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>" <?= ($profesor_id == $p['id']) ? 'selected' : '' ?>>
                    <?= $p['apellido'] . ', ' . $p['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <?php if ($profesor_id): ?>
        <div id="chat-box" class="chat-box" style="margin-top: 20px;"></div>

        <form id="form-mensaje" class="chat-form">
            <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
            <input type="hidden" name="profesor_id" value="<?= $profesor_id ?>">
            <input type="text" name="mensaje" id="mensaje" placeholder="Escribe tu mensaje..." required>
            <button type="submit">Enviar</button>
        </form>

        <script>
            function cargarMensajes() {
                fetch('ver_mensajes_chat.php?cliente_id=<?= $cliente_id ?>&profesor_id=<?= $profesor_id ?>')
                    .then(res => res.text())
                    .then(data => {
                        const box = document.getElementById('chat-box');
                        box.innerHTML = data;
                        box.scrollTop = box.scrollHeight;
                    });
            }

            document.getElementById('form-mensaje').addEventListener('submit', function(e) {
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
    <?php endif; ?>
</div>
</body>
</html>
