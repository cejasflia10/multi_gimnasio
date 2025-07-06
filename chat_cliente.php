<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red;'>Acceso denegado.</div>";
    exit;
}

// Obtener profesores
$profesores = $conexion->query("
    SELECT id, nombre, apellido 
    FROM profesores 
    WHERE gimnasio_id = $gimnasio_id
");

// Obtener otros clientes
$clientes = $conexion->query("
    SELECT id, nombre, apellido 
    FROM clientes 
    WHERE gimnasio_id = $gimnasio_id AND id != $cliente_id
");

$profesor_id = intval($_GET['profesor_id'] ?? 0);
$receptor_id = intval($_GET['cliente_receptor_id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat con profesor o alumno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">💬 Chat</h2>

    <form method="GET" class="formulario">
        <label for="profesor_id">Seleccionar profesor:</label>
        <select name="profesor_id" id="profesor_id" onchange="this.form.submit()">
            <option value="">-- Elegir profesor --</option>
            <?php while ($p = $profesores->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>" <?= ($profesor_id == $p['id']) ? 'selected' : '' ?>>
                    <?= $p['apellido'] . ', ' . $p['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <form method="GET" class="formulario" style="margin-top:10px;">
        <label for="cliente_receptor_id">Seleccionar alumno:</label>
        <select name="cliente_receptor_id" id="cliente_receptor_id" onchange="this.form.submit()">
            <option value="">-- Elegir alumno --</option>
            <?php while ($c = $clientes->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= ($receptor_id == $c['id']) ? 'selected' : '' ?>>
                    <?= $c['apellido'] . ', ' . $c['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <?php if ($profesor_id || $receptor_id): ?>
        <div id="chat-box" class="chat-box" style="margin-top: 20px;"></div>

        <form id="form-mensaje" class="chat-form">
            <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
            <?php if ($profesor_id): ?>
                <input type="hidden" name="profesor_id" value="<?= $profesor_id ?>">
            <?php endif; ?>
            <?php if ($receptor_id): ?>
                <input type="hidden" name="cliente_receptor_id" value="<?= $receptor_id ?>">
            <?php endif; ?>
            <input type="text" name="mensaje" id="mensaje" placeholder="Escribe tu mensaje..." required>
            <button type="submit">Enviar</button>
        </form>

        <script>
            function cargarMensajes() {
                let url = '';
                <?php if ($profesor_id): ?>
                    url = 'ver_mensajes_chat.php?cliente_id=<?= $cliente_id ?>&profesor_id=<?= $profesor_id ?>';
                <?php elseif ($receptor_id): ?>
                    url = 'ver_chat_clientes.php?cliente_id=<?= $cliente_id ?>&cliente_receptor_id=<?= $receptor_id ?>';
                <?php endif; ?>

                if (url) {
                    fetch(url)
                        .then(res => res.text())
                        .then(data => {
                            const box = document.getElementById('chat-box');
                            box.innerHTML = data;
                            box.scrollTop = box.scrollHeight;
                        });
                }
            }

            document.getElementById('form-mensaje').addEventListener('submit', function(e) {
                e.preventDefault();
                const datos = new FormData(this);
                let endpoint = '';
                <?php if ($profesor_id): ?>
                    endpoint = 'guardar_mensaje_chat.php';
                <?php elseif ($receptor_id): ?>
                    endpoint = 'guardar_chat_clientes.php';
                <?php endif; ?>

                fetch(endpoint, {
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
