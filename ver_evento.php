<?php
/* ============================================================
   ver_evento.php — Vista general del evento + menú del evento
   - Requiere login del módulo de eventos (evento_usuario_id)
   - Toma ?id=ID del evento (si no llega, muestra selector)
   - Fija $_SESSION['evento_id_actual'] = ID para usar en otros módulos
   - Carga menú: menu_eventos.php
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- Guardia de sesión (login de eventos) ---------- */
if (empty($_SESSION['evento_usuario_id'])) {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'ver_evento.php';
  header('Location: login_evento.php?return_to=' . urlencode($return_to));
  exit;
}

/* ---------- Conexión ---------- */
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_youtube($url){ return stripos((string)$url, 'youtube.com') !== false || stripos((string)$url, 'youtu.be') !== false; }
function yt_embed($url){
  $u = (string)$url;
  if (strpos($u, 'watch?v=') !== false) return str_replace('watch?v=', 'embed/', $u);
  if (stripos($u, 'youtu.be/') !== false) {
    $code = trim(parse_url($u, PHP_URL_PATH), '/');
    return 'https://www.youtube.com/embed/'.$code;
  }
  return $u;
}

/* ---------- Resolver evento ---------- */
$evento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* Si no viene ID: mostrar un selector simple de eventos y salir */
if ($evento_id <= 0) {
  $rs = $conexion->query("SELECT id, titulo, fecha FROM eventos_deportivos ORDER BY fecha DESC");
  ?>
  <!DOCTYPE html>
  <html lang="es">
  <head>
    <meta charset="UTF-8">
    <title>Seleccionar evento</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
      .contenedor { max-width: 900px; margin: 22px auto }
      ul.eventos { list-style: none; padding: 0 }
      ul.eventos li { padding: .45rem 0; border-bottom: 1px solid #e5e5e5 }
      a.boton { display:inline-block; padding:.45rem .7rem; background:#111; color:gold; text-decoration:none; border-radius:6px }
    </style>
  </head>
  <body>
  <div class="contenedor">
    <?php @include __DIR__ . '/menu_eventos.php'; ?>
    <h2>📅 Elegí un evento</h2>
    <?php if ($rs && $rs->num_rows > 0): ?>
      <ul class="eventos">
        <?php while($e = $rs->fetch_assoc()): ?>
          <li>
            <a class="boton" href="ver_evento.php?id=<?= (int)$e['id'] ?>">Ingresar</a>
            &nbsp; <?= h($e['fecha'] ?? '') ?> — <strong><?= h($e['titulo'] ?? '') ?></strong>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <p>No hay eventos cargados.</p>
      <p><a class="boton" href="crear_evento.php">➕ Crear evento</a></p>
    <?php endif; ?>
  </div>
  </body>
  </html>
  <?php
  exit;
}

/* Traer datos del evento */
$st = $conexion->prepare("SELECT id, titulo, descripcion, fecha, hora, lugar, flyer, video
                          FROM eventos_deportivos
                          WHERE id = ?
                          LIMIT 1");
$st->bind_param('i', $evento_id);
$st->execute();
$evento = $st->get_result()->fetch_assoc();
$st->close();

if (!$evento) {
  http_response_code(404);
  exit('⚠️ Evento no encontrado.');
}

/* Guardar el evento actual en sesión para que otros módulos lo usen */
$_SESSION['evento_id_actual'] = (int)$evento['id'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= h($evento['titulo']) ?> — Evento</title>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor { max-width:1100px; margin:20px auto }
    .grid { display:grid; grid-template-columns: 1.2fr .8fr; gap:18px }
    .card { background:#111; color:gold; padding:14px; border-radius:10px }
    .card h3 { margin-top:0 }
    .btn { display:inline-block; padding:.48rem .7rem; background:#111; color:gold; border:1px solid gold; border-radius:8px; text-decoration:none; margin:.2rem .2rem .2rem 0 }
    .muted { color:#999 }
    .flyer { max-width:100%; height:auto; border-radius:8px; border:1px solid #333; background:#000 }
    iframe { width:100%; height:280px; border:0; border-radius:8px }
    .breadcrumb a { color:gold; text-decoration:none }
    .listita { margin:0; padding-left: 1.2rem }
    .listita li { margin:.25rem 0 }
  </style>
</head>
<body>
<div class="contenedor">
  <?php @include __DIR__ . '/menu_eventos.php'; ?>

  <div class="breadcrumb" style="margin-bottom:10px;">
    <a href="panel_eventos.php">⬅ Volver al panel</a>
  </div>

  <h2>📅 <?= h($evento['titulo']) ?></h2>
  <div class="muted">
    <strong>Fecha:</strong> <?= h($evento['fecha']) ?> &nbsp;|&nbsp;
    <strong>Hora:</strong> <?= h($evento['hora']) ?> &nbsp;|&nbsp;
    <strong>Lugar:</strong> <?= h($evento['lugar']) ?>
  </div>

  <div class="grid" style="margin-top:14px;">
    <div>
      <div class="card">
        <h3>Información</h3>
        <?php if (!empty($evento['descripcion'])): ?>
          <p><?= nl2br(h($evento['descripcion'])) ?></p>
        <?php else: ?>
          <p class="muted">Sin descripción.</p>
        <?php endif; ?>

        <?php if (!empty($evento['flyer'])): ?>
          <div style="margin-top:8px;">
            <img class="flyer" src="<?= h($evento['flyer']) ?>" alt="Flyer del evento">
          </div>
        <?php endif; ?>

        <?php if (!empty($evento['video'])): ?>
          <div style="margin-top:12px;">
            <?php if (is_youtube($evento['video'])): ?>
              <iframe src="<?= h(yt_embed($evento['video'])) ?>" allowfullscreen></iframe>
            <?php else: ?>
              <a class="btn" href="<?= h($evento['video']) ?>" target="_blank">🎥 Ver video</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="card">
        <h3>Menú del evento</h3>
        <!-- Sección Entradas -->
        <p><strong>Entradas</strong></p>
        <div>
          <a class="btn" href="ver_tipos_entrada.php?evento_id=<?= (int)$evento['id'] ?>">🎫 Tipos de entradas</a>
          <a class="btn" href="vender_entrada.php?evento_id=<?= (int)$evento['id'] ?>">🛒 Vender entradas</a>
          <a class="btn" href="ver_entradas_vendidas.php?evento_id=<?= (int)$evento['id'] ?>">📥 Entradas vendidas</a>
        </div>

        <!-- Sección Competidores -->
        <p style="margin-top:10px;"><strong>Competidores</strong></p>
        <div>
          <a class="btn" href="ver_competidores_evento.php?evento_id=<?= (int)$evento['id'] ?>">🥋 Ver competidores</a>
          <a class="btn" href="agregar_competidor_evento.php?evento_id=<?= (int)$evento['id'] ?>">➕ Agregar competidor</a>
          <a class="btn" href="ver_combates_evento.php?evento_id=<?= (int)$evento['id'] ?>">🥊 Ver combates</a>
        </div>

        <!-- Sección Administración -->
        <p style="margin-top:10px;"><strong>Administración</strong></p>
        <div>
          <a class="btn" href="editar_evento.php?id=<?= (int)$evento['id'] ?>">✏️ Editar evento</a>
          <a class="btn" href="eliminar_evento.php?id=<?= (int)$evento['id'] ?>" onclick="return confirm('¿Eliminar evento?')">🗑️ Eliminar evento</a>
        </div>
      </div>

      <div class="card" style="margin-top:12px;">
        <h3>Contexto de sesión</h3>
        <ul class="listita">
          <li><code>evento_id_actual</code>: <?= (int)($_SESSION['evento_id_actual'] ?? 0) ?></li>
          <li><code>evento_usuario_id</code>: <?= (int)($_SESSION['evento_usuario_id'] ?? 0) ?></li>
        </ul>
        <p class="muted" style="margin:.5rem 0 0">Esto ayuda a que las otras pantallas tomen el evento por defecto.</p>
      </div>
    </div>
  </div>
</div>
</body>
</html>
