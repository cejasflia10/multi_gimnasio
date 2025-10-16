<?php
/* ==========================================================
   youtube_live_set.php — Configurar transmisión en vivo
   - NO pide login aquí: usa la sesión existente del sistema.
   - Si hay $_SESSION['usuario_evento_id'] y existe la tabla
     evento_organizadores => lista "mis eventos".
   - Si no, lista TODOS los eventos.
   - Guarda/edita: evento_transmision (youtube_url, pelea_inicio_id).
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
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function getI($k){ return isset($_GET[$k]) ? (int)$_GET[$k] : 0; }

/* IMPORTANTE: NO usar prepare() con SHOW TABLES */
function table_exists(mysqli $cx, string $name): bool {
  $name = $cx->real_escape_string($name);
  $sql  = "
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{$name}'
    LIMIT 1
  ";
  $res = $cx->query($sql);
  $ok  = $res && (bool)$res->fetch_row();
  if ($res instanceof mysqli_result) { $res->free(); }
  return $ok;
}

/* CSRF */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

/* ===== Sesión ya existente (NO se pide login aquí) ===== */
$usuario_evento_id = (int)($_SESSION['usuario_evento_id'] ?? 0);
$usuario_evento    = (string)($_SESSION['usuario_evento'] ?? '');
$rol_evento        = (string)($_SESSION['rol_evento'] ?? ($_SESSION['rol'] ?? ''));
$gimnasio_id       = (int)($_SESSION['gimnasio_id'] ?? 0);
$es_admin          = (strtolower($rol_evento)==='admin' || !empty($_SESSION['es_admin']));

/* ===== Tablas mínimas =====
   Solo creamos evento_transmision (1:1 por evento).
   NO es obligatorio evento_organizadores: si no existe, listamos todos.
*/
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

/* ===== Cargar eventos según contexto ===== */
$eventos = [];
$evento_sel_id = getI('evento_id');
$usar_vinculo = table_exists($conexion, 'evento_organizadores') && $usuario_evento_id > 0;

if ($usar_vinculo) {
  // “Mis eventos” vía tabla de vínculo, si existe
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
  // Sin vínculo: mostramos todos (admin / gimnasio / sesión general)
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

/* ===== Guardar/Actualizar transmisión ===== */
$msg_ok = $msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
    $msg_err = 'Token inválido. Recargá la página.';
  } else {
    $evento_id       = (int)($_POST['evento_id'] ?? 0);
    $youtube_url     = trim((string)($_POST['youtube_url'] ?? ''));
    $pelea_inicio_id = $_POST['pelea_inicio_id'] === '' ? null : (int)$_POST['pelea_inicio_id'];
    $activo          = isset($_POST['activo']) ? 1 : 0;

    if ($evento_id <= 0 || $youtube_url === '') {
      $msg_err = 'Seleccioná un evento y pegá el enlace de YouTube.';
    } else {
      // Si hay tabla de vínculo y usuario_evento_id, validamos permiso (salvo admin)
      if ($usar_vinculo && !$es_admin) {
        $sqlOwn = "SELECT 1 FROM evento_organizadores WHERE evento_id={$evento_id} AND usuario_evento_id={$usuario_evento_id} LIMIT 1";
        $resOwn = $conexion->query($sqlOwn);
        $own    = $resOwn && (bool)$resOwn->fetch_row();
        if ($resOwn instanceof mysqli_result) $resOwn->free();
        if (!$own) {
          $msg_err = 'No tenés permiso para este evento.';
        }
      }

      if (!$msg_err) {
        $evento_id_i = (int)$evento_id;
        $youtube_url_i = $conexion->real_escape_string($youtube_url);
        $pelea_val = is_null($pelea_inicio_id) ? "NULL" : (int)$pelea_inicio_id;
        $activo_i = (int)$activo;

        $sqlUp = "
          INSERT INTO evento_transmision (evento_id, youtube_url, pelea_inicio_id, activo)
          VALUES ({$evento_id_i}, '{$youtube_url_i}', {$pelea_val}, {$activo_i})
          ON DUPLICATE KEY UPDATE
            youtube_url = VALUES(youtube_url),
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
}

/* ===== Cargar transmisión del evento seleccionado (si hay) ===== */
$transmision = null;
if ($evento_sel_id > 0) {
  $evento_sel_id_i = (int)$evento_sel_id;
  $sqlTr = "SELECT youtube_url, pelea_inicio_id, activo FROM evento_transmision WHERE evento_id={$evento_sel_id_i} LIMIT 1";
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
    .row3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px}
    .btn{display:inline-block;background:var(--brand);color:#000;padding:10px 14px;border-radius:10px;font-weight:700;border:0;cursor:pointer}
    .btn.sec{background:#2b2b2b;color:#fff}
    .msg{padding:10px 12px;border-radius:10px;margin-bottom:10px}
    .ok{background:#13341a;border:1px solid #2e7d32}
    .err{background:#3a1313;border:1px solid #b71c1c}
    small{color:var(--muted)}
    a{color:var(--brand);text-decoration:none}
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
    <form method="post" class="card">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="evento_id" value="<?=$evento_sel_id?>">

      <h3 style="margin:0 0 8px">Configuración de YouTube</h3>
      <div class="row3">
        <div>
          <label>URL de YouTube (live o video)</label>
          <input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=XXXX"
                 value="<?=h($transmision['youtube_url'] ?? '')?>" required>
          <small>Pegá el enlace completo.</small>
        </div>
        <div>
          <label>ID de la primera pelea (opcional)</label>
          <input type="number" name="pelea_inicio_id" min="1"
                 value="<?=h((string)($transmision['pelea_inicio_id'] ?? ''))?>">
          <small>Corresponde a <code>peleas_evento.id</code>.</small>
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

      <div style="margin-top:12px">
        <button class="btn">Guardar transmisión</button>
        <?php if (!empty($transmision['youtube_url'])): ?>
          <a class="btn sec" style="margin-left:8px"
             href="transmision_en_vivo.php?evento_id=<?=$evento_sel_id?>" target="_blank">Ver transmisión</a>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>

  <div class="card">
    <h3 style="margin:0 0 8px">¿Cómo se usa?</h3>
    <ol style="margin:0 0 0 18px">
      <li>Entrás con tu sesión normal (no hay login aquí).</li>
      <li>Elegís el evento (si existe <code>evento_organizadores</code> y tenés <code>usuario_evento_id</code>, verás solo los tuyos; si no, todos).</li>
      <li>Pegás el <b>link de YouTube</b> y (opcional) el <b>ID de la primera pelea</b>.</li>
      <li>Guardás y abrís <code>transmision_en_vivo.php?evento_id=...</code> con el botón.</li>
    </ol>
  </div>
</div>
</body>
</html>
