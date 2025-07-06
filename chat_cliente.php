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
    SELECT id, nombre, apellido, 'profesor' AS tipo 
    FROM profesores 
    WHERE gimnasio_id = $gimnasio_id
");

// Obtener alumnos (excepto a sí mismo)
$clientes = $conexion->query("
    SELECT id, nombre, apellido, 'cliente' AS tipo 
    FROM clientes 
    WHERE gimnasio_id = $gimnasio_id AND id != $cliente_id
");

// Combinar resultados en array
$contactos = [];
while ($p = $profesores->fetch_assoc()) $contactos[] = $p;
while ($c = $clientes->fetch_assoc()) $contactos[] = $c;

// Conversación activa
$tipo = $_GET['tipo'] ?? '';
$receptor_id = intval($_GET['id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat General</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .chat-layout {
            display: flex;
            flex-direction: row;
            gap: 20px;
        }

        .lista-contactos {
            width: 100%;
            max-width: 250px;
            border-right: 1px solid #333;
        }

        .lista-contactos ul {
            list-style: none;
            padding: 0;
        }

        .lista-contactos li {
            margin: 10px 0;
        }

        .lista-contactos a {
            color: gold;
            text-decoration: none;
            display: block;
        }

        .chat-area {
            flex: 1;
        }

        @media(max-width: 768px) {
            .chat-layout {
                flex-direction: column;
            }

            .lista-contactos {
                max-width: 100%;
                border: none;
            }
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">💬 Chat General</h2>

    <div class="chat-layout">
        <div class="lista-contactos">
            <h4>Contactos</h4>
            <ul>
                <?php foreach ($contactos as $contacto): ?>
                    <li>
                        <a href="?tipo=<?= $contacto['tipo'] ?>&id=<?= $contacto['id'] ?>">
                            <?= $contacto['apellido'] . ', ' . $contacto['nombre'] ?>
                            <?= ($contacto['tipo'] === 'profesor') ? '👨‍🏫' : '👤' ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="chat-area">
            <?php if ($receptor_id && in_array($tipo, ['profesor', 'cliente'])): ?>
                <div id="chat-box" class="chat-box" style="margin-bottom:10px;"></div>

                <form id="form-mensaje" class="chat-form">
                    <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
                    <?php if ($tipo === 'profesor'): ?>
                        <input type="hidden" name="profesor_id" value="<?= $receptor_id ?>">
                    <?php else: ?>
                        <input type="hidden" name="cliente_receptor_id" value="<?= $receptor_id ?>">
                    <?php endif; ?>
                    <input type="text" name="mensaje" id="mensaje" placeholder="Escribe tu mensaje..." required>
                    <button type="submit">Enviar</button>
                </form>

                <script>
                    function cargarMensajes() {
                        let url = '';
                        <?php if ($tipo === 'profesor'): ?>
                        url = 'ver_mensajes_chat.php?cliente_id=<?= $cliente_id ?>&profesor_id=<?= $receptor_id ?>';
                        <?php else: ?>
                        url = 'ver_chat_clientes.php?cliente_id=<?= $cliente_id ?>&cliente_receptor_id=<?= $receptor_id ?>';
                        <?php endif; ?>

                        fetch(url)
                            .then(res => res.text())
                            .then(data => {
                                document.getElementById('chat-box').innerHTML = data;
                                document.getElementById('chat-box').scrollTop = document.getElementById('chat-box').scrollHeight;
                            });
                    }

                    document.getElementById('form-mensaje').addEventListener('submit', function (e) {
                        e.preventDefault();
                        const datos = new FormData(this);
                        let action = 'guardar_mensaje_chat.php';
                        <?php if ($tipo === 'cliente'): ?>
                        action = 'guardar_chat_clientes.php';
                        <?php endif; ?>

                        fetch(action, {
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
            <?php else: ?>
                <p>Selecciona un contacto para comenzar a chatear.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
