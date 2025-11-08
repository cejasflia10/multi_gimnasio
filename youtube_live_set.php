<?php
/* ==========================================================
   youtube_live_set.php — Configurar transmisión en vivo (YouTube / StreamYard)
   - Usa sesión existente (NO login aquí).
   - Si hay $_SESSION['usuario_evento_id'] y existe evento_organizadores,
     lista “mis eventos”. Si no, lista todos.
   - Guarda/edita en evento_transmision:
       • youtube_url (watch/live)
       • embed_url   (ej: https://streamyard.com/watch/XXXX o vimeo/twitch permitido)
       • pelea_inicio_id (opcional)
       • activo
   - Botones de vista previa abren SIEMPRE: vivo.php (YouTube/EMBED)
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function getI($k){ return isset($_GET[$k]) ? (int)$_GET[$k] : 0; }

/* Tabla existe */
function table_exists(mysqli $cx, string $name): bool {
  $name = $cx->real_escape_string($name);
  $sql  = "SELECT 1 FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$name}' LIMIT 1";
  $res = $cx->query($sql);
  $ok  = $res && (bool)$res->fetch_row();
  if ($res instanceof mysqli_result) $res->free();
  return $ok;
}

/* Columna existe */
function col_exists(mysqli $cx, string $table, string $col): bool {
  $t = $cx->real_escape_string($table);
  $c = $cx->real_escape_string($col);
  $sql = "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '{$t}'
            AND COLUMN_NAME = '{$c}'
          LIMIT 1";
  $res = $cx->query($sql);
  $ok  = $res && (bool)$res->fetch_row();
  if ($res instanceof mysqli_result) $res->free();
  return $ok;
}

/* Extraer ID de YouTube desde URL watch/live/shorts/etc */
function youtube_id_from_url(string $url): ?string {
  $u = trim($url);
  if ($u==='') return null;
  // http(s)://www.youtube.com/watch?v=XXXX
  if (preg_match('~[?&]v=([A-Za-z0-9_-]{6,})~', $u, $m)) return $m[1];
  // https://youtu.be/XXXX
  if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $u, $m)) return $m[1];
  // https://www.youtube.com/live/XXXX
  if (preg_match('~youtube\.com/(?:live|embed)/([A-Za-z0-9_-]{6,})~', $u, $m)) return $m[1];
  return null;
}

/* Validar dominios de EMBED permitidos (StreamYard, Vimeo, Twitch, YouTube) */
function allowed_embed_src(?string $url): ?string {
  $u = trim((string)$url);
  if ($u==='') return null;
  $p = @parse_url($u);
  if (!$p || empty($p['scheme']) || empty($p['host'])) return null;
  $host = strtolower($p['host']);
  $allowed = [
    'streamyard.com','www.streamyard.com',
    'youtube.com','www.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com',
    'youtu.be',
    'player.vimeo.com','vimeo.com','www.vimeo.com',
    'player.twitch.tv','twitch.tv','www.twitch.tv',
  ];
  foreach ($allowed as $ok) {
    if ($host === $ok || str_ends_with($host, '.'.$ok)) return $u;
  }
  return null;
}

/* ===== Sesión existente ===== */
$usuario_evento_id = (int)($_SESSION['usuario_evento_id'] ?? 0);
$usuario_evento    = (string)($_SESSION['usuario_evento'] ?? '');
$rol_evento        = (string)($_SESSION['rol_evento'] ?? ($_SESSION['rol'] ?? ''));
$gimnasio_id       = (int)($_SESSION['gimnasio_id'] ?? 0);
$es_admin          = (strtolower($rol_evento)==='admin' || !empty($_SESSION['es_admin']));

/* ===== Estructura mínima ===== */
$conexion->query("
  CREATE TABLE IF NOT EXISTS evento_transmision (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL UNIQUE,
    youtube_url VARCHAR(255) NOT NULL,
    pelea_inicio_id INT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
/* Asegurar columna embed_url (para StreamYard u otros) */
if (!col_exists($conexion, 'evento_transmision', 'embed_url')) {
  $conexion->query("ALTER TABLE evento_transmision ADD COLUMN embed_url VARCHAR(255) NULL AFTER youtube_url");
}

/* ===== Cargar eventos ===== */
$eventos = [];
$evento_sel_id = getI('evento_id');
$usar_vinculo = table_exists($conexion, 'evento_organizadores') && $usuario_evento_id > 0;

if ($usar_vinculo) {
  $sql = "
    SELECT e.id,
           COALESCE(NULLIF(TRIM(e.titulo),''), CONCAT('Evento #', e.id)) AS titulo,
           e.fecha, e.lugar
    FROM eventos_deportivos e
    INNER JOIN evento_organizadores eo ON eo.evento_id = e.id
    WHERE eo.usuario_evento_id = {$usuario_evento_id}
    ORDER BY COALESCE(e.fecha,'1900-01-01') DESC, e.id DESC
  ";
  $res = $conexion->query($sql);
  while ($res && $row = $res->fetch_assoc()) $eventos[] = $row;
  if ($res instanceof mysqli_result) $res->free();
} else {
  $sql = "
    SELECT e.id,
           COALESCE(NULLIF(TRIM(e.titulo),''), CONCAT('Evento #', e.id)) AS titulo,
           e.fecha, e.lugar
    FROM eventos_deportivos e
    ORDER BY COALESCE(e.fecha,'1900-01-01') DESC, e.id DESC
  ";
  $res = $conexion->query($sql);
  while ($res && $row = $res->fetch_assoc()) $eventos[] = $row;
  if ($res instanceof mysqli_result) $res->free();
}

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

/* ===== Guardar/Actualizar ===== */
$msg_ok = $msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
    $msg_err = 'Token inválido. Recargá la página.';
  } else {
    $evento_id       = (int)($_POST['evento_id'] ?? 0);
    $youtube_url     = trim((string)($_POST['youtube_url'] ?? ''));
    $embed_url_raw   = trim((string)($_POST['embed_url'] ?? ''));
    $embed_url       = $embed_url_raw ? (allowed_embed_src($embed_url_raw) ?? '') : '';
    $pelea_inicio_id = $_POST['pelea_inicio_id'] === '' ? null : (int)$_POST['pelea_inicio_id'];
    $activo          = isset($_POST['activo']) ? 1 : 0;

    if ($evento_id <= 0 || $youtube_url === '') {
      // Permitimos guardar sin YouTube si se provee EMBED
      if ($evento_id <= 0 || ($youtube_url === '' && $embed_url === '')) {
        $msg_err = 'Seleccioná un evento y pegá al menos un enlace (YouTube o EMBED).';
      }
    }

    // Validar permisos si hay vínculo y no es admin
    if (!$msg_err && $usar_vinculo && !$es_admin) {
      $sqlOwn = "SELECT 1 FROM evento_organizadores WHERE evento_id={$evento_id} AND usuario_evento_id={$usuario_evento_id} LIMIT 1";
      $resOwn = $conexion->query($sqlOwn);
      $own    = $resOwn && (bool)$resOwn->fetch_row();
      if ($resOwn instanceof mysqli_result) $resOwn->free();
      if (!$own) $msg_err = 'No tenés permiso para este evento.';
    }

    if (!$msg_err) {
      $evento_id_i = (int)$evento_id;
      $youtube_url_i = $conexion->real_escape_string($youtube_url);
      $embed_url_i   = $conexion->real_escape_string($embed_url);
      $pelea_val = is_null($pelea_inicio_id) ? "NULL" : (int)$pelea_inicio_id;
      $activo_i = (int)$activo;

      $sqlUp = "
        INSERT INTO evento_transmision (evento_id, youtube_url, embed_url, pelea_inicio_id, activo)
        VALUES ({$evento_id_i}, '{$youtube_url_i}', '{$embed_url_i}', {$pelea_val}, {$activo_i})
        ON DUPLICATE KEY UPDATE
          youtube_url = VALUES(youtube_url),
          embed_url = VALUES(embed_url),
          pelea_inicio_id = VALUES(pelea_inicio_id),
          activo = VALUES(activo),
          actualizado_en = CURRENT_TIMESTAMP
      ";
      if ($conexion->query($sqlUp) === true) {
        $msg_ok = 'Transmisión guardada correctamente.';
      } else {
        $msg_err = 'Error guardando: '.h($conexion->error);
      }
    }
  }
}

/* ===== Cargar transmisión seleccionada ===== */
$transmision = null;
if ($evento_sel_id > 0) {
  $evento_sel_id_i = (int)$evento_sel_id;
  $sqlTr = "SELECT youtube_url, embed_url, pelea_inicio_id, activo
            FROM evento_transmision
            WHERE evento_id={$evento_sel_id_i} LIMIT 1";
  $resTr = $conexion->query($sqlTr);
  $transmision = ($resTr && $resTr->num_rows) ? $resTr->fetch_assoc() : null;
  if ($resTr instanceof mysqli_result) $resTr->free();
}

/* ===== UI ===== */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Transmisión en vivo — Configurar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --bg:#111; --ink:#eee; --muted:#aaa; --brand:#ffd600; --card:#1c1c1c; }
    html,body{background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:0}
    header{position:sticky;top:0;background:#000a;padding:12px 16px;border-bottom:1px solid #222}
    header h1{margin:0;font-size:18px}
    .wrap{max-width:920px;margin:0 auto;padding:16px}
    .card{background:var(--card);border:1px solid #2a2a2a;border-radius:12px;padding:16px;margin-bottom:16px}
    label{display:block;font-size:13px;margin:10px 0 6px;color:var(--muted)}
    input,select{width:100%;padding:10px;border-radius:10px;border:1px solid #333;background:#101010;color:var(--ink)}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .row3{display:grid;grid-template-columns:2fr 2fr 1fr;gap:12px}
    .btn{display:inline-block;background:var(--brand);color:#000;padding:10px 14px;border-radius:10px;font-weight:700;border:0;cursor:pointer}
    .btn.sec{background:#2b2b2b;color:#fff}
    .msg{padding:10px 12px;border-radius:10px;margin-bottom:10px}
    .ok{background:#13341a;border:1px solid #2e7d32}
    .err{background:#3a1313;border:1px solid #b71c1c}
    small{color:var(--muted)}
    a{color:var(--brand);text-decoration:none}
    .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
  </style>
</head>
<body>
<header>
  <h1>🎥 Transmisión en vivo — Configurar</h1>
</header>

<div class="wrap">
  <div class="card" style="font-size:14px;color:var(--muted)">
    Sesión:
    <?= $usuario_evento ? ('Organizador <b>'.h($usuario_evento).'</b> · ') : '' ?>
    <?= $es_admin ? '<b>Admin</b>' : 'Usuario del sistema' ?>
    <?= $gimnasio_id ? (' · Gimnasio #'.(int)$gimnasio_id) : '' ?>
  </div>

  <div class="card">
    <h3 style="margin:0 0 8px">Elegí el evento</h3>
    <?php if (!$eventos): ?>
      <div class="msg err">⚠️ No hay eventos para mostrar.</div>
    <?php else: ?>
      <form method="get" class="row" style="margin-top:8px">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <div>
          <label>Evento</label>
          <select name="evento_id" onchange="this.form.submit()">
            <option value="">— seleccionar —</option>
            <?php foreach($eventos as $ev): ?>
              <option value="<?=$ev['id']?>" <?=($ev['id']==$evento_sel_id?'selected':'')?>>
                [#<?=$ev['id']?>] <?=h($ev['titulo'])?> <?= $ev['fecha']?('· '.h($ev['fecha'])):'' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>&nbsp;</label>
          <button class="btn">Abrir</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($msg_ok): ?><div class="msg ok"><?=h($msg_ok)?></div><?php endif; ?>
  <?php if ($msg_err): ?><div class="msg err"><?=h($msg_err)?></div><?php endif; ?>

  <?php if ($evento_sel_id>0 && $eventos): ?>
    <form method="post" class="card" autocomplete="off">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="evento_id" value="<?=$evento_sel_id?>">

      <h3 style="margin:0 0 8px">Enlaces de transmisión</h3>
      <div class="row3">
        <div>
          <label>URL de YouTube (live o video)</label>
          <input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=XXXX"
                 value="<?=h($transmision['youtube_url'] ?? '')?>">
          <small>Podés pegar el enlace completo (watch/live/short/...).</small>
        </div>
        <div>
          <label>EMBED (StreamYard / Vimeo / Twitch / YouTube)</label>
          <input type="url" name="embed_url" placeholder="https://streamyard.com/watch/XXXX"
                 value="<?=h($transmision['embed_url'] ?? '')?>">
          <small>Acepta dominios permitidos (ej: streamyard.com).</small>
        </div>
        <div>
          <label>Activo</label>
          <?php $act = isset($transmision['activo']) ? (int)$transmision['activo'] : 1; ?>
          <select name="activo">
            <option value="1" <?=$act===1?'selected':''?>>Sí</option>
            <option value="0" <?=$act===0?'selected':''?>>No</option>
          </select>
        </div>
      </div>

      <div class="row" style="margin-top:10px">
        <div>
          <label>ID de la primera pelea (opcional)</label>
          <input type="number" name="pelea_inicio_id" min="1"
                 value="<?=h((string)($transmision['pelea_inicio_id'] ?? ''))?>">
          <small>Corresponde a <code>peleas_evento.id</code>.</small>
        </div>
        <div>
          <label>Vista previa</label>
          <div class="actions">
            <?php
              $ytid = youtube_id_from_url((string)($transmision['youtube_url'] ?? ''));
              $embed = allowed_embed_src((string)($transmision['embed_url'] ?? '')) ?: '';
              $baseVivo = "vivo.php?evento_id=".$evento_sel_id;
              if ($ytid) {
                $hrefYT = $baseVivo . "&ytid=" . rawurlencode($ytid) . "&autoplay=1&mute=1";
                echo '<a class="btn sec" href="'.h($hrefYT).'" target="_blank" rel="noopener">Ver en vivo (YouTube)</a>';
              }
              if ($embed) {
                $hrefEM = $baseVivo . "&embed=" . rawurlencode($embed) . "&autoplay=1&mute=1";
                echo '<a class="btn sec" href="'.h($hrefEM).'" target="_blank" rel="noopener">Ver en vivo (EMBED)</a>';
              }
              if (!$ytid && !$embed) {
                echo '<span style="color:#bbb;font-size:13px">Guardá un enlace para habilitar la vista previa.</span>';
              }
            ?>
          </div>
        </div>
      </div>

      <div style="margin-top:12px">
        <button class="btn">Guardar transmisión</button>
        <a class="btn sec" style="margin-left:8px"
           href="vivo.php?evento_id=<?=$evento_sel_id?>" target="_blank" rel="noopener">Abrir vivo.php (auto)</a>
      </div>
    </form>
  <?php endif; ?>

  <div class="card">
    <h3 style="margin:0 0 8px">¿Cómo se usa?</h3>
    <ol style="margin:0 0 0 18px">
      <li>Elegís el evento (si existe <code>evento_organizadores</code> y tenés <code>usuario_evento_id</code>, verás solo los tuyos).</li>
      <li>Pegás <b>URL de YouTube</b> (si transmitís a YouTube con StreamYard) y/o <b>EMBED</b> (por ejemplo, StreamYard <code>/watch/</code>).</li>
      <li>Guardás. Podés previsualizar:
        <ul>
          <li><b>YouTube:</b> abre <code>vivo.php?evento_id=...&ytid=...</code></li>
          <li><b>EMBED:</b> abre <code>vivo.php?evento_id=...&embed=...</code></li>
          <li>Sin parámetros, <code>vivo.php</code> decide: HLS → EMBED → YouTube.</li>
        </ul>
      </li>
      <li>(Opcional) Definí <b>pelea_inicio_id</b> para alinear la “pelea actual”.</li>
    </ol>
  </div>
</div>
</body>
</html>
