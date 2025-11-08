<?php
// player_hls.php — Reproductor simple para HLS
// Uso: player_hls.php?base=https://rtmp-tuapp.onrender.com&stream=pelea1
$base   = rtrim($_GET['base'] ?? '', '/');
$stream = preg_replace('~[^a-zA-Z0-9_\-]~','', $_GET['stream'] ?? 'pelea1');
$src    = $base ? "{$base}/hls/{$stream}.m3u8" : "/hls/{$stream}.m3u8";
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Live HLS — <?=htmlspecialchars($stream) ?></title>
<style>
  body{margin:0;background:#000;color:#fff;font-family:system-ui,Segoe UI,Roboto,Arial}
  .wrap{max-width:1200px;margin:0 auto;padding:12px}
  video{width:100%;max-height:80vh;background:#000;outline:none}
  .info{opacity:.8;font-size:14px;margin-top:8px}
  a{color:#9fd3ff}
</style>
</head>
<body>
<div class="wrap">
  <video id="v" controls playsinline muted></video>
  <div class="info">
    Fuente HLS: <code><?=htmlspecialchars($src)?></code>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
const src = <?= json_encode($src) ?>;
const video = document.getElementById('v');

function playHLS(){
  if (Hls.isSupported()){
    const hls = new Hls({ lowLatencyMode:true, backBufferLength:90 });
    hls.loadSource(src);
    hls.attachMedia(video);
    hls.on(Hls.Events.MANIFEST_PARSED, () => video.play());
  } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = src;
    video.addEventListener('loadedmetadata', () => video.play());
  } else {
    alert('Tu navegador no soporta HLS.');
  }
}
playHLS();
</script>
</body>
</html>
