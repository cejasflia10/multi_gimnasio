<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$cliente_id || !$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

// Obtener profesores y otros clientes del gimnasio
$profesores = $conexion->query("
    SELECT id, nombre, apellido 
    FROM profesores 
    WHERE gimnasio_id = $gimnasio_id
");

$alumnos = $conexion->query("
    SELECT id, nombre, apellido 
    FROM clientes 
    WHERE gimnasio_id = $gimnasio_id AND id != $cliente_id
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat General</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .chat-contenedor {
            display: flex;
            flex-direction: column;
        }

        .contactos {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
        }

        .contacto {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #444;
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

        #buscador {
            margin-bottom: 10px;
            padding: 8px;
            width: 100%;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">💬 Chat General</h2>

    <input type="text" id="buscador" placeholder="Buscar contacto...">

    <div id="lista-contactos" class="contactos">
        <?php while ($p = $profesores->fetch_assoc()): ?>
            <div class="contacto" data-nombre="<?= strtolower($p['apellido'] . ' ' . $p['nombre']) ?>"
                 onclick="cargarChat('profesor', <?= $p['id'] ?>, '<?= $p['apellido'] . ', ' . $p['nombre'] ?>')">
                🧑‍🏫 <?= $p['apellido'] . ', ' . $p['nombre'] ?>
            </div>
        <?php endwhile; ?>
        <?php while ($c = $alumnos->fetch_assoc()): ?>
            <div class="contacto" data-nombre="<?= strtolower($c['apellido'] . ' ' . $c['nombre']) ?>"
                 onclick="cargarChat('cliente', <?= $c['id'] ?>, '<?= $c['apellido'] . ', ' . $c['nombre'] ?>')">
                🧑 <?= $c['apellido'] . ', ' . $c['nombre'] ?>
            </div>
        <?php endwhile; ?>
    </div>

    <div id="chat-titulo" class="titulo-seccion oculto"></div>
    <div id="chat-box" class="chat-box oculto"></div>

    <form id="form-mensaje" class="chat-form oculto">
        <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
        <input type="hidden" id="destino_tipo" name="destino_tipo">
        <input type="hidden" id="destino_id" name="destino_id">
        <input type="text" name="mensaje" id="mensaje" placeholder="Escribe tu mensaje..." required>
        <button type="submit">Enviar</button>
    </form>
</div>

<script>
    const buscador = document.getElementById('buscador');
    buscador.addEventListener('input', function () {
        const texto = this.value.toLowerCase();
        document.querySelectorAll('.contacto').forEach(el => {
            el.style.display = el.dataset.nombre.includes(texto) ? 'block' : 'none';
        });
    });

    function cargarChat(tipo, id, nombre) {
        document.getElementById('chat-titulo').classList.remove('oculto');
        document.getElementById('chat-box').classList.remove('oculto');
        document.getElementById('form-mensaje').classList.remove('oculto');

        document.getElementById('chat-titulo').innerText = "💬 Conversación con: " + nombre;
        document.getElementById('destino_tipo').value = tipo;
        document.getElementById('destino_id').value = id;

        const url = `cargar_chat_cliente.php?tipo=${tipo}&id=${id}`;
        fetch(url)
            .then(res => res.text())
            .then(html => {
                document.getElementById('chat-box').innerHTML = html;
                const box = document.getElementById('chat-box');
                box.scrollTop = box.scrollHeight;
            });
    }

    document.getElementById('form-mensaje').addEventListener('submit', function (e) {
        e.preventDefault();
        const datos = new FormData(this);
        fetch('enviar_mensaje_general.php', {
            method: 'POST',
            body: datos
        }).then(() => {
            document.getElementById('mensaje').value = '';
            cargarChat(
                document.getElementById('destino_tipo').value,
                document.getElementById('destino_id').value,
                document.getElementById('chat-titulo').innerText.replace('💬 Conversación con: ', '')
            );
        });
    });
</script>
</body>
</html>
