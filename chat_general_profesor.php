<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$profesor_id || !$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

// Obtener alumnos que hayan reservado clases con este profesor
$clientes = $conexion->query("
    SELECT DISTINCT c.id, c.nombre, c.apellido 
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.id_profesor = $profesor_id AND t.gimnasio_id = $gimnasio_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat con Alumnos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .contacto {
            padding: 8px;
            border-bottom: 1px solid #444;
            cursor: pointer;
        }

        .contacto:hover {
            background-color: #222;
        }

        .chat-box {
            border: 1px solid #555;
            padding: 10px;
            height: 300px;
            overflow-y: auto;
            margin-bottom: 10px;
        }

        #buscador {
            margin-bottom: 10px;
            padding: 8px;
            width: 100%;
        }

        .chat-form {
            display: flex;
            gap: 10px;
        }

        .chat-form input[type="text"] {
            flex: 1;
            padding: 10px;
        }

        .oculto {
            display: none;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">💬 Chat con Alumnos</h2>

    <input type="text" id="buscador" placeholder="Buscar alumno...">

    <div id="lista-contactos">
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <div class="contacto" data-nombre="<?= strtolower($c['apellido'] . ' ' . $c['nombre']) ?>"
                 onclick="cargarChat(<?= $c['id'] ?>, '<?= $c['apellido'] . ', ' . $c['nombre'] ?>')">
                🧑 <?= $c['apellido'] . ', ' . $c['nombre'] ?>
            </div>
        <?php endwhile; ?>
    </div>

    <div id="chat-titulo" class="titulo-seccion oculto"></div>
    <div id="chat-box" class="chat-box oculto"></div>

    <form id="form-mensaje" class="chat-form oculto">
        <input type="hidden" name="profesor_id" value="<?= $profesor_id ?>">
        <input type="hidden" id="cliente_id" name="cliente_id">
        <input type="text" name="mensaje" id="mensaje" placeholder="Escribe tu mensaje..." required>
        <button type="submit">Enviar</button>
    </form>
</div>

<script>
    document.getElementById('buscador').addEventListener('input', function () {
        const texto = this.value.toLowerCase();
        document.querySelectorAll('.contacto').forEach(el => {
            el.style.display = el.dataset.nombre.includes(texto) ? 'block' : 'none';
        });
    });

    function cargarChat(clienteId, nombre) {
        document.getElementById('chat-titulo').innerText = "💬 Conversación con: " + nombre;
        document.getElementById('chat-titulo').classList.remove('oculto');
        document.getElementById('chat-box').classList.remove('oculto');
        document.getElementById('form-mensaje').classList.remove('oculto');
        document.getElementById('cliente_id').value = clienteId;

        fetch('cargar_chat_profesor.php?cliente_id=' + clienteId)
            .then(res => res.text())
            .then(html => {
                document.getElementById('chat-box').innerHTML = html;
                document.getElementById('chat-box').scrollTop = document.getElementById('chat-box').scrollHeight;
            });
    }

    document.getElementById('form-mensaje').addEventListener('submit', function (e) {
        e.preventDefault();
        const datos = new FormData(this);
        fetch('enviar_mensaje_profesor.php', {
            method: 'POST',
            body: datos
        }).then(() => {
            document.getElementById('mensaje').value = '';
            cargarChat(document.getElementById('cliente_id').value,
                document.getElementById('chat-titulo').innerText.replace('💬 Conversación con: ', ''));
        });
    });
</script>
</body>
</html>
