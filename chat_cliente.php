<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red;'>Acceso denegado.</div>";
    exit;
}

$clientes = $conexion->query("
    SELECT id, nombre, apellido 
    FROM clientes 
    WHERE gimnasio_id = $gimnasio_id AND id != $cliente_id
");

$receptor_id = intval($_GET['cliente_receptor_id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat con otros alumnos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">💬 Chat con otros alumnos</h2>

    <form method="GET" class="formulario">
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

    <?php if ($receptor_id): ?>
        <div id="chat-box" class="chat-box" style="margin-top: 20px;"></div>

        <form id="form-mensaje" class="chat-form">
            <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
            <input type="hidden" name="cliente_receptor_id" value="<?= $receptor_id ?>">
            <input type="text" name="mensaje" id="mensaje" placeholder="Escribe tu mensaje..." required>
            <button type="submit">Enviar</button>
        </form>

        <script>
            function cargarMensajes() {
                fetch('ver_chat_clientes.php?cliente_id=<?= $cliente_id ?>&cliente_receptor_id=<?= $receptor_id ?>')
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
                fetch('guardar_chat_clientes.php', {
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
