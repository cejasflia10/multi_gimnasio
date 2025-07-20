<?php
session_start();
include 'conexion.php';
include 'menu_eventos.php';

$eventos = $conexion->query("SELECT * FROM eventos_deportivos ORDER BY fecha DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📅 Ver Eventos</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .evento-card {
            background-color: #111;
            border: 1px solid #555;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            color: gold;
        }

        .evento-card img.flyer {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 10px 0;
        }

        .video-thumbnail {
            width: 100%;
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 10px;
        }

        .boton-compra {
            display: inline-block;
            padding: 10px 20px;
            background-color: gold;
            color: black;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
            font-weight: bold;
        }

        .titulo-seccion {
            color: gold;
            text-align: center;
            margin-bottom: 25px;
        }

        #modalVideo {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        #modalVideo iframe {
            width: 90%;
            height: 90%;
            border: none;
            border-radius: 10px;
        }

        #modalVideo .cerrar {
            position: absolute;
            top: 20px;
            right: 30px;
            background: red;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        @media(max-width: 600px) {
            #modalVideo iframe {
                width: 100%;
                height: 60%;
            }
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2 class="titulo-seccion">📅 Eventos Deportivos</h2>

    <?php while ($e = $eventos->fetch_assoc()): ?>
        <div class="evento-card">
            <h3>📌 <?= htmlspecialchars($e['titulo']) ?></h3>
            <p><strong>📅 Fecha:</strong> <?= date('d/m/Y', strtotime($e['fecha'])) ?> - ⏰ <?= substr($e['hora'], 0, 5) ?></p>
            <p><strong>📍 Lugar:</strong> <?= htmlspecialchars($e['lugar']) ?></p>

            <?php if (!empty($e['descripcion'])): ?>
                <p><strong>📝 Descripción:</strong> <?= nl2br(htmlspecialchars($e['descripcion'])) ?></p>
            <?php endif; ?>

            <?php if (!empty($e['flyer']) && file_exists($e['flyer'])): ?>
                <img src="<?= $e['flyer'] ?>" alt="Flyer del evento" class="flyer">
            <?php endif; ?>

            <?php if (!empty($e['video'])):
                $video_url = $e['video'];
                $video_id = '';
                if (strpos($video_url, 'watch?v=') !== false) {
                    $video_id = explode('watch?v=', $video_url)[1];
                } elseif (strpos($video_url, 'youtu.be/') !== false) {
                    $video_id = explode('youtu.be/', $video_url)[1];
                }
                if ($video_id):
                    $thumbnail_url = "https://img.youtube.com/vi/{$video_id}/0.jpg";
            ?>
                <img src="<?= $thumbnail_url ?>" class="video-thumbnail" onclick="abrirModalVideo('https://www.youtube.com/embed/<?= $video_id ?>')" alt="Video">
            <?php endif; endif; ?>

<a href="vender_entrada.php?evento_id=<?= $e['id'] ?>" class="boton-compra">🎫 Comprar Entrada</a>
        </div>
    <?php endwhile; ?>

    <a href="crear_evento.php" class="boton">➕ Crear nuevo evento</a>
</div>

<!-- Modal para reproducir video -->
<div id="modalVideo" style="display:none; flex-direction: column;">
    <button class="cerrar" onclick="cerrarModalVideo()">✖ Cerrar</button>
    <iframe id="iframeVideo" src="" allowfullscreen></iframe>
</div>

<script>
function abrirModalVideo(url) {
    document.getElementById('iframeVideo').src = url;
    document.getElementById('modalVideo').style.display = 'flex';
}
function cerrarModalVideo() {
    document.getElementById('modalVideo').style.display = 'none';
    document.getElementById('iframeVideo').src = '';
}
</script>

</body>
</html>
