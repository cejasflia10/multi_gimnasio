<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$profesor_id || !$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

$clientes = $conexion->query("
    SELECT id, nombre, apellido 
    FROM clientes 
    WHERE gimnasio_id = $gimnasio_id
    ORDER BY apellido, nombre
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat con Alumnos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">💬 Chat con Alumnos</h2>

    <label for="cliente_id">Seleccionar alumno:</label>
    <select id="cliente_id" onchange="cargarChat()">
        <option value="">-- Elegir alumno --</option>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>">
                <?= $c['apellido'] . ', ' . $c['nombre'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <div id="chat-box" class="chat-box" style="display:none;"></div>

    <form id="form-mensaje" class="chat-form" style="display:none;" method="POST">
        <input type="hidden" name="cliente_id" id="cliente_id_input">
        <input type="text" name="mensaje" id="mensaje" placeholder="Escribe tu mensaje..." required>
        <button type="submit">Enviar</button>
    </form>
</div>

<script>
function cargarChat() {
    const clienteId = document.getElementById("cliente_id").value;
    if (!clienteId) return;

    document.getElementById("cliente_id_input").value = clienteId;

    fetch("cargar_chat_profesor.php?cliente_id=" + clienteId)
        .then(res => res.text())
        .then(html => {
            document.getElementById("chat-box").innerHTML = html;
            document.getElementById("chat-box").style.display = "block";
            document.getElementById("form-mensaje").style.display = "flex";
        });
}

document.getElementById("form-mensaje").addEventListener("submit", function(e) {
    e.preventDefault();
    const form = e.target;
    const datos = new FormData(form);
    fetch("enviar_mensaje_profesor.php", {
        method: "POST",
        body: datos
    }).then(() => {
        document.getElementById("mensaje").value = "";
        cargarChat();
    });
});
</script>
</body>
</html>
