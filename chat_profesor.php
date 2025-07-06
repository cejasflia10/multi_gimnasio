<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($profesor_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red; text-align:center;'>Acceso denegado</div>";
    exit;
}

// Obtener lista de alumnos que reservaron hoy
$fecha_hoy = date('Y-m-d');
$alumnos = $conexion->query("
    SELECT DISTINCT c.id, c.nombre, c.apellido
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.id_profesor = $profesor_id AND r.fecha = '$fecha_hoy'
");

$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat con Clientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h2 class="titulo-seccion">💬 Chat con Alumnos</h2>

    <form method="GET" class="formulario">
        <label for="cliente_id">Seleccionar alumno:</label>
        <select name="cliente_id" id="cliente_id" onchange="this.form.submit()">
            <option value="">-- Elegir alumno --</option>
            <?php while ($alumno = $alumnos->fetch_assoc()): ?>
                <option value="<?= $alumno['id'] ?>" <?= ($cliente_id == $alumno['id']) ? 'selected' : '' ?>>
                    <?= $alumno['apellido'] . ', ' . $alumno['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <?php if ($cliente_id): ?>
        <div id="chat-box" class="chat-box" style="margin-top: 20px;"></div>

        <form id="form-mensaje" class="chat-form">
            <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
            <input type="hidden" name="profesor_id" value="<?= $profesor_id ?>">
            <input type="text" name="mensaje" id="mensaje" placeholder="Escribe tu mensaje..." required autocomplete="off">
            <button type="submit">Enviar</button>
        </form>

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
    <?php endif; ?>
</div>

</body>
</html>
