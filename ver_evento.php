<?php
/* ============================================================
   ver_evento.php — Vista general del evento + menú del evento (responsive)
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();

/* Guardia de sesión (login de eventos) */
if (empty($_SESSION['evento_usuario_id'])) {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'ver_evento.php';
  header('Location: login_evento.php?return_to=' . urlencode($return_to));
  exit;
}

/* Conexión */
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ No hay conexión a la base de datos.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
function is_youtube($url){ $u=(string)$url; return stripos($u,'youtube.com')!==false || stripos($u,'youtu.be')!==false || stripos($u,'shorts/')!==false; }
function yt_embed($url){
  $u=(string)$url;
  if (strpos($u,'watch?v=')!==false) return str_replace('watch?v=','embed/',$u);
  if (stripos($u,'shorts/')!==false) return str_ireplace('shorts/','embed/',$u);
  if (stripos($u,'youtu.be/')!==false){ $code=trim(parse_url($u,PHP_URL_PATH),'/'); return 'https://www.youtube.com/embed/'.$code; }
  return $u;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* Resolver evento */
$evento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* Si no viene ID: selector simple (responsive) */
if ($evento_id <= 0) {
  $rs = $conexion->query("SELECT id, titulo, fecha FROM eventos_deportivos ORDER BY fecha DESC");
  ?>
  <!DOCTYPE html>
  <html lang="es">
  <head>
    <meta charset="UTF-8">
    <title>Seleccionar evento</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
      :root{
        --brand:#d4af37; --line:#222; --card:#111; --fg:#f6f6f6; --muted:#c9c9c9;
      }
      html,body{background:#0a0a0a;color:var(--fg);margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}
      a{color:var(--brand);text-decoration:none}
      .contenedor{max-width:1100px;margin:22px auto;padding:0 12px}
      h2{margin:8px 0 16px}
      .lista{display:grid;grid-template-columns:1fr;gap:10px;margin:0;padding:0;list-style:none}
      .item{
        background:var(--card); border:1px solid var(--line); border-radius:12px; padding:12px;
        display:flex; align-items:center; justify-content:space-between; gap:10px;
      }
      .item .meta{color:var(--muted);font-size:.95rem}
      .btn{
        display:inline-flex;align-items:center;gap:.45rem;padding:.55rem .85rem;background:#111;
        color:var(--brand);border-radius:10px;border:1px solid var(--line);font-weight:600;flex:0 0 auto;
      }
      @media (min-width:700px){ .lista{grid-template-columns:1fr 1fr} }
      @media (min-width:1000px){ .lista{grid-template-columns:1fr 1fr 1fr} }
    </style>
  </head>
  <body>
  <div class="contenedor">
    <?php @include __DIR__ . '/menu_eventos.php'; ?>
    <h2>📅 Elegí un evento</h2>

    <?php if ($rs && $rs->num_rows > 0): ?>
      <ul class="lista">
        <?php while($e=$rs->fetch_assoc()): ?>
          <li class="item">
            <div>
              <div class="meta"><?= h($e['fecha'] ?? '') ?></div>
              <strong><?= h($e['titulo'] ?? '') ?></strong>
            </div>
            <a class="btn" href="ver_evento.php?id=<?= (int)$e['id'] ?>">Ingresar</a>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <p>No hay eventos cargados.</p>
      <p><a class="btn" href="crear_evento.php">➕ Crear evento</a></p>
    <?php endif; ?>
  </div>
  </body>
  </html>
  <?php
  exit;
}

/* Traer datos del evento */
$st = $conexion->prepare("SELECT id, titulo, descripcion, fecha, hora, lugar, flyer, video FROM eventos_deportivos WHERE id = ? LIMIT 1");
$st->bind_param('i', $evento_id);
$st->execute(); $evento = $st->get_result()->fetch_assoc(); $st->close();
if (!$evento) { http_response_code(404); exit('⚠️ Evento no encontrado.'); }

/* Guardar el evento actual en sesión */
$_SESSION['evento_id_actual'] = (int)$evento['id'];

/* ====== CONFIG / MIGRACIONES SUAVES PARA MÓDULOS ====== */
$conexion->query("CREATE TABLE IF NOT EXISTS eventos_pagos_config (
  evento_id INT PRIMARY KEY,
  alias_bancario VARCHAR(120) NULL,
  titular_banco  VARCHAR(200) NULL,
  banco_nombre   VARCHAR(200) NULL,
  habilitar_online  TINYINT(1) NOT NULL DEFAULT 1,
  habilitar_taquilla TINYINT(1) NOT NULL DEFAULT 1,
  nota TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (evento_id) REFERENCES eventos_deportivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!has_col($conexion,'pedidos','origen'))           { @$conexion->query("ALTER TABLE pedidos ADD COLUMN origen ENUM('online','taquilla') NOT NULL DEFAULT 'online' AFTER total"); }
if (!has_col($conexion,'pedidos','metodo_pago'))      { @$conexion->query("ALTER TABLE pedidos ADD COLUMN metodo_pago VARCHAR(50) NULL AFTER origen"); }
if (!has_col($conexion,'pedidos','alias_usado'))      { @$conexion->query("ALTER TABLE pedidos ADD COLUMN alias_usado VARCHAR(120) NULL AFTER metodo_pago"); }
if (!has_col($conexion,'pedidos','cuenta_destino'))   { @$conexion->query("ALTER TABLE pedidos ADD COLUMN cuenta_destino VARCHAR(200) NULL AFTER alias_usado"); }
if (!has_col($conexion,'pedidos','comprobante_path')) { @$conexion->query("ALTER TABLE pedidos ADD COLUMN comprobante_path VARCHAR(255) NULL AFTER cuenta_destino"); }
if (!has_col($conexion,'pedidos','estado'))           { @$conexion->query("ALTER TABLE pedidos ADD COLUMN estado ENUM('pendiente','aprobado','pagado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente' AFTER comprobante_path"); }
if (!has_col($conexion,'pedidos','created_at'))       { @$conexion->query("ALTER TABLE pedidos ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"); }

if (!has_col($conexion,'tickets','qr_path'))   { @$conexion->query("ALTER TABLE tickets ADD COLUMN qr_path VARCHAR(255) NULL"); }
if (!has_col($conexion,'tickets','used_at'))   { @$conexion->query("ALTER TABLE tickets ADD COLUMN used_at DATETIME NULL"); }
if (!has_col($conexion,'tickets','used_by'))   { @$conexion->query("ALTER TABLE tickets ADD COLUMN used_by INT NULL"); }
if (!has_col($conexion,'tickets','used_gate')) { @$conexion->query("ALTER TABLE tickets ADD COLUMN used_gate VARCHAR(60) NULL"); }

/* Cargar config de pagos para las pills */
$cfg = ['alias_bancario'=>null,'titular_banco'=>null,'banco_nombre'=>null,'habilitar_online'=>1,'habilitar_taquilla'=>1,'nota'=>null];
$st=$conexion->prepare("SELECT * FROM eventos_pagos_config WHERE evento_id=?");
$st->bind_param('i',$evento_id); $st->execute(); $r=$st->get_result(); if($r && $r->num_rows){ $cfg=$r->fetch_assoc(); } $st->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title><?= h($evento['titulo']) ?> — Evento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    :root{
      --bg:#0a0a0a; --fg:#f6f6f6; --muted:#c9c9c9; --brand:#d4af37; --card:#111; --line:#222; --danger:#ff6b6b;
    }
    html,body{background:var(--bg); color:var(--fg); margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}
    a{color:var(--brand); text-decoration:none}
    a:focus{outline:2px dashed var(--brand); outline-offset:2px}
    .contenedor{max-width:1100px; margin:20px auto; padding:0 12px}
    h2{margin:8px 0 10px}
    .muted{color:var(--muted)}
    .breadcrumb{margin-bottom:10px}
    .breadcrumb a{color:var(--brand)}

    /* Grid principal */
    .grid{
      display:grid; grid-template-columns:1.2fr .8fr; gap:18px; align-items:start;
    }
    @media (max-width: 980px){
      .grid{ grid-template-columns:1fr; gap:14px; }
    }

    .card{
      background:var(--card); color:var(--fg); padding:14px; border-radius:12px;
      border:1px solid var(--line);
    }
    .card h3{margin:0 0 10px 0}

    .btn{
      display:inline-flex; align-items:center; gap:.45rem; padding:.58rem .9rem;
      background:#121212; color:var(--brand); border-radius:10px; border:1px solid var(--line);
      font-weight:600; margin:.25rem .35rem .25rem 0;
    }
    .btn.sec{ background:#1a1a1a; color:#ddd; border-color:#444 }

    /* Media responsive */
    .media{ display:grid; gap:10px }
    .flyer{ width:100%; height:auto; border-radius:10px; border:1px solid var(--line); background:#000 }
    .video-embed{ width:100%; aspect-ratio:16/9; border:0; border-radius:10px; background:#000 }

    /* Pills */
    .pill{display:inline-block;padding:.15rem .55rem;border:1px solid #555;border-radius:999px;font-size:.85rem;color:#ddd;margin-left:.3rem}

    /* Botonera: apilar en móvil */
    .btn-row{display:flex; flex-wrap:wrap}
    @media (max-width:560px){
      .btn{flex:1 1 100%}
      .btn-row{gap:6px}
    }
  </style>
</head>
<body>
<div class="contenedor">
  <?php @include __DIR__ . '/menu_eventos.php'; ?>

  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="panel_eventos.php">⬅ Volver al panel</a>
  </nav>

  <h2>📅 <?= h($evento['titulo']) ?></h2>
  <div class="muted" style="margin-bottom:8px">
    <strong>Fecha:</strong> <?= h($evento['fecha']) ?> &nbsp;|&nbsp;
    <strong>Hora:</strong> <?= h($evento['hora']) ?> &nbsp;|&nbsp;
    <strong> Lugar:</strong> <?= h($evento['lugar']) ?>
  </div>

  <div class="grid" style="margin-top:6px">
    <!-- Columna principal -->
    <section>
      <article class="card">
        <h3>Información</h3>
        <?php if (!empty($evento['descripcion'])): ?>
          <p><?= nl2br(h($evento['descripcion'])) ?></p>
        <?php else: ?>
          <p class="muted">Sin descripción.</p>
        <?php endif; ?>

        <div class="media">
          <?php if (!empty($evento['flyer'])): ?>
            <img class="flyer" src="<?= h($evento['flyer']) ?>" alt="Flyer del evento">
          <?php endif; ?>

          <?php if (!empty($evento['video'])): ?>
            <?php if (is_youtube($evento['video'])): ?>
              <iframe class="video-embed" src="<?= h(yt_embed($evento['video'])) ?>" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            <?php else: ?>
              <a class="btn" href="<?= h($evento['video']) ?>" target="_blank" rel="noopener">🎥 Ver video</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </article>

      <article class="card" style="margin-top:12px">
        <h3>Ventas públicas</h3>
        <p class="muted" style="margin:.2rem 0 .6rem">Página pública y catálogo de entradas para el público general.</p>
        <div class="btn-row">
          <a class="btn" href="eventos_disponibles.php" target="_blank" rel="noopener">🌐 Listado público</a>
          <a class="btn" href="evento.php?id=<?= (int)$evento['id'] ?>" target="_blank" rel="noopener">👀 Vista pública de este evento</a>
          <a class="btn" href="comprar_entradas.php?evento_id=<?= (int)$evento['id'] ?>" target="_blank" rel="noopener">🧾 Comprar entradas (público)</a>
          <a class="btn sec" href="ver_tipos_entrada.php?evento_id=<?= (int)$evento['id'] ?>">⚙️ Tipos de entradas</a>
        </div>
      </article>
    </section>

    <!-- Columna lateral -->
    <aside>
      <div class="card">
        <h3>Menú del evento</h3>

        <p><strong>Entradas</strong></p>
        <div class="btn-row">
          <a class="btn" href="vender_entrada.php?evento_id=<?= (int)$evento['id'] ?>">🛒 Vender en taquilla</a>
          <a class="btn" href="ver_entradas_vendidas.php?evento_id=<?= (int)$evento['id'] ?>">📥 Entradas vendidas</a>
          <a class="btn" href="ver_ventas_evento.php?evento_id=<?= (int)$evento['id'] ?>">📊 Ventas y rendición</a>
        </div>

        <p style="margin-top:10px"><strong>Accesos (QR)</strong></p>
        <div class="btn-row">
          <a class="btn" href="scan_tickets.php?evento_id=<?= (int)$evento['id'] ?>">🔎 Escanear tickets (QR)</a>
        </div>

        <p style="margin-top:10px">
          <strong>Formas de pago</strong>
          <?php if(!empty($cfg['alias_bancario'])): ?><span class="pill">Alias: <?= h($cfg['alias_bancario']) ?></span><?php endif; ?>
          <?php if((int)$cfg['habilitar_online']===1): ?><span class="pill">Online: ON</span><?php else: ?><span class="pill">Online: OFF</span><?php endif; ?>
          <?php if((int)$cfg['habilitar_taquilla']===1): ?><span class="pill">Taquilla: ON</span><?php else: ?><span class="pill">Taquilla: OFF</span><?php endif; ?>
        </p>
        <div class="btn-row">
          <a class="btn" href="config_pagos_evento.php?evento_id=<?= (int)$evento['id'] ?>">💳 Configurar pagos</a>
        </div>

        <p style="margin-top:10px"><strong>Competidores</strong></p>
        <div class="btn-row">
          <a class="btn" href="ver_competidores_evento.php?evento_id=<?= (int)$evento['id'] ?>">🥋 Ver competidores</a>
          <a class="btn" href="agregar_competidor_evento.php?evento_id=<?= (int)$evento['id'] ?>">➕ Agregar competidor</a>
          <a class="btn" href="ver_combates_evento.php?evento_id=<?= (int)$evento['id'] ?>">🥊 Ver combates</a>
        </div>

        <p style="margin-top:10px"><strong>Administración</strong></p>
        <div class="btn-row">
          <a class="btn sec" href="editar_evento.php?id=<?= (int)$evento['id'] ?>">✏️ Editar evento</a>
          <a class="btn sec" href="eliminar_evento.php?id=<?= (int)$evento['id'] ?>" onclick="return confirm('¿Eliminar evento?')">🗑️ Eliminar evento</a>
        </div>
      </div>

      <div class="card" style="margin-top:12px">
        <h3>Contexto de sesión</h3>
        <ul style="margin:0 0 .4rem 1.1rem">
          <li><code>evento_id_actual</code>: <?= (int)($_SESSION['evento_id_actual'] ?? 0) ?></li>
          <li><code>evento_usuario_id</code>: <?= (int)($_SESSION['evento_usuario_id'] ?? 0) ?></li>
        </ul>
        <p class="muted" style="margin:.2rem 0 0">Esto ayuda a que las otras pantallas tomen el evento por defecto.</p>
      </div>
    </aside>
  </div>
</div>
</body>
</html>
