<?php
// panel_cliente.php — versión moderna + PROMOS "flash" + RUTINAS/ARCHIVOS persistentes
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

if ($cliente_id === 0 || $gimnasio_id === 0) {
    header("Location: cliente_acceso.php");
    exit;
}

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function col_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $sql = "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  $rs = $db->query($sql);
  return $rs && $rs->num_rows > 0;
}
function fmt_bytes($b){
  $b=(float)$b; $u=['B','KB','MB','GB','TB']; $i=0;
  while($b>=1024 && $i<count($u)-1){ $b/=1024; $i++; }
  return number_format($b, ($i>1?2:0), ',', '.') . ' ' . $u[$i];
}

/* === Helpers para visor y Cloudinary (no modifican BD) === */
function is_image_ext(string $ext): bool {
  $ext = strtolower($ext);
  return in_array($ext, ['jpg','jpeg','png','gif','webp'], true);
}
function is_pdf_ext(string $ext): bool {
  return strtolower($ext) === 'pdf';
}
/**
 * Corrige al vuelo URLs de Cloudinary para ver PDFs inline.
 * Si detecta /image/upload/ en una URL cuyo archivo es PDF, la cambia a /raw/upload/.
 * No guarda cambios, solo para el <iframe>.
 */
function cld_viewer_url(string $url, string $ext): string {
  if (!is_pdf_ext($ext)) return $url;
  // Solo tocar si es de Cloudinary
  if (preg_match('#^https?://res\.cloudinary\.com/[^/]+/image/upload/#i', $url)) {
    $url = preg_replace('#/image/upload/#i', '/raw/upload/', $url, 1);
  }
  return $url;
}

/* Polyfill str_starts_with para PHP < 8 */
if (!function_exists('str_starts_with')) {
  function str_starts_with($haystack, $needle) {
    return $needle === '' || strpos($haystack, $needle) === 0;
  }
}

/* ===== Resolver de foto de cliente (robusto) ===== */
function resolve_cliente_foto(array $cli): string {
  // 1) Si hay base64 válida, usarla
  $b64 = (string)($cli['foto_base64'] ?? '');
  if ($b64 !== '' && str_starts_with($b64, 'data:image')) return $b64;

  // 2) Candidatos de columna (AGREGADO: foto_path primero)
  $candidatos = [];
  foreach (['foto_path','foto_url','foto','avatar','imagen','perfil_foto'] as $k) {
    if (!empty($cli[$k])) $candidatos[] = (string)$cli[$k];
  }

  // 3) Probar cada candidato
  $carpetas = [
    '', // por si el valor ya incluye la subcarpeta correcta
    'fotos_clientes',
    'uploads',
    'public/uploads',
    'img',
    'images',
    'fotos',
    'clientes',
    'media',
  ];

  foreach ($candidatos as $cand) {
    $cand = trim($cand);

    // URL absoluta
    if (preg_match('#^https?://#i', $cand)) return $cand;

    // Si ya viene con subcarpeta (ej: uploads/nico.jpg), probar tal cual
    if (strpos($cand, '/') !== false) {
      $abs = __DIR__ . '/' . $cand;
      if (is_file($abs)) {
        $mtime = @filemtime($abs) ?: time();
        // Normalizar cada segmento con rawurlencode
        $parts = array_map('rawurlencode', array_filter(explode('/', $cand), 'strlen'));
        return implode('/', $parts) . '?v=' . $mtime;
      }
    }

    // Probar en carpetas conocidas
    foreach ($carpetas as $dir) {
      $rel = ($dir ? ($dir . '/') : '') . $cand;
      $abs = __DIR__ . '/' . $rel;
      if (is_file($abs)) {
        $mtime = @filemtime($abs) ?: time();
        $parts = array_map('rawurlencode', array_filter(explode('/', $rel), 'strlen'));
        return implode('/', $parts) . '?v=' . $mtime;
      }
    }
  }

  // 4) Fallback: default local si existe
  $default = __DIR__ . '/fotos_clientes/default.png';
  if (is_file($default)) {
    $v = @filemtime($default) ?: time();
    return 'fotos_clientes/default.png?v='.$v;
  }

  // 5) Fallback final: data-URI SVG (círculo gris con iniciales)
  $nombre = trim(($cli['apellido'] ?? '').' '.($cli['nombre'] ?? ''));
  $inic   = '';
  foreach (explode(' ', $nombre) as $w) { $w = trim($w); if ($w!=='') { $inic .= mb_strtoupper(mb_substr($w,0,1,'UTF-8'),'UTF-8'); } }
  $inic = $inic !== '' ? $inic : '👤';
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">
    <rect width="200" height="200" rx="100" ry="100" fill="#2b2f36"/>
    <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Inter, Arial" font-size="72" fill="#f5c542">'.$inic.'</text>
  </svg>';
  return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
}

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Validar cliente ===== */
$cliente = null;
if ($stmt = $conexion->prepare("SELECT * FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1")) {
    $stmt->bind_param("ii", $cliente_id, $gimnasio_id);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$cliente) {
    header("Location: cliente_acceso.php");
    exit;
}

/* ===== Completar Datos Físicos (idéntico a tu versión) ===== */
if ((int)($cliente['datos_completos'] ?? 0) === 0) {
    $mensaje = "";
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_datos_fisicos'])) {
        $peso         = trim((string)($_POST['peso'] ?? ''));
        $altura       = trim((string)($_POST['altura'] ?? ''));
        $remera       = trim((string)($_POST['talle_remera'] ?? ''));
        $pantalon     = trim((string)($_POST['talle_pantalon'] ?? ''));
        $calzado      = trim((string)($_POST['talle_calzado'] ?? ''));
        $observaciones= trim((string)($_POST['observaciones'] ?? ''));
        $enfermedades = trim((string)($_POST['enfermedades'] ?? ''));
        $medicacion   = trim((string)($_POST['medicacion'] ?? ''));
        $fecha        = date('Y-m-d');

        if ($stmtInsert = $conexion->prepare("
            INSERT INTO datos_fisicos 
              (cliente_id, gimnasio_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, observaciones, enfermedades, medicacion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")) {
            $stmtInsert->bind_param(
              "iisssssssss",
              $cliente_id, $gimnasio_id, $fecha, $peso, $altura, $remera, $pantalon, $calzado, $observaciones, $enfermedades, $medicacion
            );
            if ($stmtInsert->execute()) {
                $conexion->query("UPDATE clientes SET datos_completos=1 WHERE id={$cliente_id} AND gimnasio_id={$gimnasio_id}");
                header("Location: panel_cliente.php");
                exit;
            } else {
                $mensaje = "❌ Error al guardar los datos. Intente nuevamente.";
            }
            $stmtInsert->close();
        } else {
            $mensaje = "❌ Error interno al preparar el guardado.";
        }
    }
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8" />
      <title>Completar Datos Físicos</title>
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <style>
        :root{ --bg:#0b0b0b; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12); }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--fg);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial}
        .wrap{min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{width:100%;max-width:420px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:18px;padding:20px}
        h2{margin:0 0 12px;font:800 22px/1.2 Inter,system-ui} p{margin:0 0 16px;color:var(--muted)}
        label{display:block;margin:10px 0 6px;font-weight:700;font-size:14px}
        input,textarea{width:100%;padding:10px;border-radius:12px;border:1px solid var(--border);background:#0f1115;color:var(--fg);font-size:14px}
        textarea{min-height:70px}
        .btn{width:100%;margin-top:12px;padding:12px;border:none;border-radius:14px;background:var(--acc);color:#111;font-weight:800;cursor:pointer}
        .msg{margin-bottom:10px;color:#ff6b6b;font-weight:700}
      </style>
    </head>
    <body>
      <div class="wrap">
        <form class="card" method="POST" autocomplete="off">
          <h2>📋 Completar Datos Físicos</h2>
          <p>Completá tus medidas y observaciones para personalizar tus entrenamientos.</p>
          <?php if (!empty($mensaje)): ?><div class="msg"><?= h($mensaje) ?></div><?php endif; ?>
          <input type="hidden" name="guardar_datos_fisicos" value="1" />
          <label>Peso (kg)</label><input name="peso" required />
          <label>Altura (cm)</label><input name="altura" required />
          <label>Talle Remera</label><input name="talle_remera" />
          <label>Talle Pantalón</label><input name="talle_pantalon" />
          <label>Talle Calzado</label><input name="talle_calzado" />
          <label>Observaciones</label><textarea name="observaciones"></textarea>
          <label>Enfermedades (si tiene)</label><textarea name="enfermedades"></textarea>
          <label>Medicaciones (si toma)</label><textarea name="medicacion"></textarea>
          <button class="btn" type="submit">Guardar datos</button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

/* ===== Datos base para panel ===== */
$cliente_nombre = trim(($cliente['apellido'] ?? '').' '.($cliente['nombre'] ?? ''));
$tz   = new DateTimeZone('America/Argentina/San_Luis');
$hoyD = new DateTime('today', $tz);
$hoy  = $hoyD->format('Y-m-d');
$fecha_filtro = isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha']) ? $_GET['fecha'] : $hoy;

/* ===== Membresía ===== */
$membresia = null;
if ($stmtM1 = $conexion->prepare("
  SELECT clases_disponibles, fecha_vencimiento
  FROM membresias
  WHERE cliente_id=? AND gimnasio_id=? AND fecha_vencimiento >= ?
  ORDER BY fecha_vencimiento ASC
  LIMIT 1
")) {
  $stmtM1->bind_param("iis", $cliente_id, $gimnasio_id, $hoy);
  $stmtM1->execute();
  $membresia = $stmtM1->get_result()->fetch_assoc();
  $stmtM1->close();
}
if (!$membresia && $stmtM2 = $conexion->prepare("
  SELECT clases_disponibles, fecha_vencimiento
  FROM membresias
  WHERE cliente_id=? AND gimnasio_id=?
  ORDER BY fecha_vencimiento DESC
  LIMIT 1
")) {
  $stmtM2->bind_param("ii", $cliente_id, $gimnasio_id);
  $stmtM2->execute();
  $membresia = $stmtM2->get_result()->fetch_assoc();
  $stmtM2->close();
}

/* ===== Foto (usando el resolver robusto) ===== */
$foto_url = resolve_cliente_foto($cliente);

/* ===== Alertas membresía ===== */
$alerta_membresia_html = '';
if ($membresia) {
  $clases    = max(0, (int)($membresia['clases_disponibles'] ?? 0));
  $vto_raw   = (string)($membresia['fecha_vencimiento'] ?? '');
  $vtoD      = DateTime::createFromFormat('Y-m-d', $vto_raw, $tz) ?: new DateTime($vto_raw ?: 'now', $tz);
  $diffSigned= (int)$hoyD->diff($vtoD)->format('%r%a');
  $dias_rest = max(0, $diffSigned);

  if ($clases <= 2 || $dias_rest <= 3) {
    $t_clase = ($clases === 1 ? 'clase' : 'clases');
    $t_dia   = ($dias_rest === 1 ? 'día' : 'días');
    $estado  = ($diffSigned < 0) ? 'Vencida' : "Vence en <strong>{$dias_rest} {$t_dia}</strong>";
    $alerta_membresia_html = '
    <div class="alerta alerta-amarilla">
      <div class="ico">⚠️</div>
      <div>
        <strong>¡Atención!</strong> Te quedan <strong>'.h((string)$clases).'</strong> '.$t_clase.'.<br>
        '.$estado.' (vence: <strong>'.h($vtoD->format('Y-m-d')).'</strong>)
      </div>
    </div>';
  }
} else {
  $alerta_membresia_html = '
  <div class="alerta alerta-gris">
    No encontramos una membresía registrada. ¿Querés activar un plan?
  </div>';
}

/* ===== PROMOCIONES ===== */
$promos = [];
$hasCols = [
  'gimnasio_id' => col_exists($conexion,'promociones','gimnasio_id'),
  'titulo'      => col_exists($conexion,'promociones','titulo'),
  'descripcion' => col_exists($conexion,'promociones','descripcion'),
  'imagen_url'  => col_exists($conexion,'promociones','imagen_url') || col_exists($conexion,'promociones','imagen'),
  'link_url'    => col_exists($conexion,'promociones','link_url'),
  'color_fondo' => col_exists($conexion,'promociones','color_fondo'),
  'color_texto' => col_exists($conexion,'promociones','color_texto'),
  'fecha_inicio'=> col_exists($conexion,'promociones','fecha_inicio'),
  'fecha_fin'   => col_exists($conexion,'promociones','fecha_fin'),
  'prioridad'   => col_exists($conexion,'promociones','prioridad'),
  'activo'      => col_exists($conexion,'promociones','activo'),
];

$colsSelect = ['id'];
foreach (['titulo','descripcion','link_url','color_fondo','color_texto'] as $c) {
  if ($hasCols[$c]) $colsSelect[] = $c;
}
if ($hasCols['imagen_url']) {
  if (!col_exists($conexion,'promociones','imagen_url') && col_exists($conexion,'promociones','imagen')) {
    $colsSelect[] = "imagen AS imagen_url";
  } else {
    $colsSelect[] = "imagen_url";
  }
}
if ($hasCols['fecha_inicio']) $colsSelect[] = 'fecha_inicio';
if ($hasCols['fecha_fin'])    $colsSelect[] = 'fecha_fin';
if ($hasCols['prioridad'])    $colsSelect[] = 'prioridad';
if ($hasCols['activo'])       $colsSelect[] = 'activo';
if ($hasCols['gimnasio_id'])  $colsSelect[] = 'gimnasio_id';

$select = implode(',', $colsSelect);
$where  = [];
if ($hasCols['gimnasio_id']) $where[] = "gimnasio_id = {$gimnasio_id}";
if ($hasCols['activo'])      $where[] = "activo = 1";
if ($hasCols['fecha_inicio'])$where[] = "(fecha_inicio IS NULL OR fecha_inicio <= '{$hoy}')";
if ($hasCols['fecha_fin'])   $where[] = "(fecha_fin IS NULL OR fecha_fin >= '{$hoy}')";

$order = "ORDER BY ";
$orderParts = [];
if ($hasCols['activo'])    $orderParts[] = "activo DESC";
if ($hasCols['prioridad']) $orderParts[] = "prioridad DESC";
if ($hasCols['fecha_fin']) $orderParts[] = "fecha_fin DESC";
$orderParts[] = "id DESC";
$order .= implode(', ', $orderParts);

$sqlPromos = "SELECT {$select} FROM promociones " .
             (empty($where) ? "" : ("WHERE ".implode(' AND ', $where)." ")).$order;

$rsP = $conexion->query($sqlPromos);
if ($rsP) {
  while($p = $rsP->fetch_assoc()){
    if (empty($p['color_fondo'])) $p['color_fondo'] = '#111111';
    if (empty($p['color_texto'])) $p['color_texto'] = '#FFD700';
    $img = trim((string)($p['imagen_url'] ?? ''));
    if ($img !== '') {
      if (!preg_match('#^https?://#i', $img)) {
        $localPath = __DIR__ . '/promos/' . $img;
        $img = is_file($localPath) ? ('promos/' . rawurlencode($img)) : '';
      }
    }
    $p['imagen_resuelta'] = $img;
    $promos[] = $p;
  }
  $rsP->free();
}

/* ====== RUTINAS/ARCHIVOS (con persistencia por URL externa) ====== */
/* Tabla de “visto” (idempotente) */
$conexion->query("
  CREATE TABLE IF NOT EXISTS rutinas_vistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    rutina_id INT NOT NULL,
    visto_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cli_rut (cliente_id, rutina_id),
    INDEX idx_cli (cliente_id),
    INDEX idx_rut (rutina_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* Tabla de rutinas/archivos enviados por el profesor (si no existe) */
$conexion->query("
  CREATE TABLE IF NOT EXISTS rutinas_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    gimnasio_id INT NOT NULL,
    profesor_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    url_archivo TEXT NOT NULL,
    extension VARCHAR(16) DEFAULT '',
    tamano_bytes INT DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cli (cliente_id),
    INDEX idx_gym (gimnasio_id),
    INDEX idx_fecha (creado_en)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* Marcar como visto (POST al mismo archivo) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_visto'], $_POST['rutina_id'])) {
  $rid = (int)$_POST['rutina_id'];
  // Validar pertenencia
  $okRut = false;
  if ($st = $conexion->prepare("SELECT 1 FROM rutinas_clientes WHERE id=? AND cliente_id=? AND gimnasio_id=? LIMIT 1")) {
    $st->bind_param('iii', $rid, $cliente_id, $gimnasio_id);
    $st->execute(); $st->store_result(); $okRut = $st->num_rows > 0; $st->close();
  }
  if ($okRut) {
    if ($st2 = $conexion->prepare("
      INSERT INTO rutinas_vistas (cliente_id, rutina_id) VALUES (?, ?)
      ON DUPLICATE KEY UPDATE visto_en = CURRENT_TIMESTAMP
    ")) {
      $st2->bind_param('ii', $cliente_id, $rid);
      $st2->execute(); $st2->close();
    }
  }
  // Volver al panel para evitar reenvío
  header("Location: panel_cliente.php");
  exit;
}

/* Traer rutinas/archivos del profesor con flag visto
   - Por defecto 10; si ?todo=1, hasta 100 (para historial). */
$rut_limit = (isset($_GET['todo']) && $_GET['todo'] == '1') ? 100 : 10;
$rutinas = [];
if ($stmtN = $conexion->prepare("
  SELECT r.id, r.nombre_archivo, r.url_archivo, r.extension, r.tamano_bytes, r.creado_en,
         COALESCE(CONCAT(p.apellido, ', ', p.nombre), CONCAT('ID ', r.profesor_id)) AS profesor,
         CASE WHEN v.id IS NULL THEN 0 ELSE 1 END AS visto
  FROM rutinas_clientes r
  LEFT JOIN profesores p ON p.id = r.profesor_id
  LEFT JOIN rutinas_vistas v ON v.rutina_id = r.id AND v.cliente_id = ?
  WHERE r.gimnasio_id = ? AND r.cliente_id = ?
  ORDER BY r.creado_en DESC, r.id DESC
  LIMIT ?
")) {
  $stmtN->bind_param('iiii', $cliente_id, $gimnasio_id, $cliente_id, $rut_limit);
  $stmtN->execute();
  $rutinas = $stmtN->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtN->close();
}

/* ===== Menú cliente ===== */
include __DIR__ . '/menu_cliente.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Panel del Cliente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{ margin:0; background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg);
           color:var(--fg); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .container{ max-width:1100px; margin:0 auto; padding:16px 16px 48px; }
    .glass{ background: rgba(255,255,255,.05); border:1px solid var(--border); border-radius:20px; backdrop-filter: blur(10px); box-shadow: 0 8px 30px rgba(0,0,0,.35); }
    .header{ display:flex; flex-direction:column; gap:16px; align-items:center; text-align:center; padding:18px }
    @media (min-width:740px){ .header{ flex-direction:row; text-align:left; align-items:flex-end; } }
    .avatar{ width:110px; height:110px; border-radius:50%; object-fit:cover; border:4px solid var(--acc); }
    .badge{ margin-top:6px; display:inline-block; font-size:12px; padding:4px 8px; border-radius:999px; background: rgba(245,197,66,.15); color:var(--acc); border:1px solid rgba(245,197,66,.35); }
    .title{ margin:0; font-weight:800; line-height:1.1; font-size:26px }
    .subtitle{ margin:6px 0 0; color:var(--muted); font-size:14px }
    .btn{ display:inline-block; padding:10px 16px; border-radius:14px; border:1px solid var(--border); color:var(--fg); text-decoration:none; font-weight:700; font-size:14px; }
    .btn:hover{ border-color:#ffffff33 }
    .btn-primary{ background:var(--acc); color:#111; border:none; }
    .grid{ display:grid; gap:16px; margin-top:18px; grid-template-columns: 1fr; }
    @media (min-width:740px){ .grid{ grid-template-columns: repeat(3, 1fr);} .col-span-2{ grid-column: span 2; } }
    .card{ padding:18px }
    .dl{ display:flex; flex-direction:column; gap:10px; font-size:14px; color:var(--muted) }
    .row{ display:flex; justify-content:space-between; gap:12px }
    .val{ color:var(--fg) }
    /* Alertas */
    .alerta{ display:flex; gap:12px; align-items:flex-start; padding:12px 14px; border-radius:14px; margin:14px auto 0; max-width:800px; }
    .alerta .ico{ font-size:20px; line-height:1 }
    .alerta-amarilla{ background: rgba(255, 193, 7, .12); border:1px solid rgba(255, 193, 7, .35); color:#ffc107; }
    .alerta-gris{ background: rgba(108,117,125,.12); border:1px solid rgba(108,117,125,.35); color:#ced4da; }
    /* Reservas */
    .filter{ display:flex; align-items:center; gap:10px; margin-bottom:10px }
    .filter input[type="date"]{ background:#0f1115; color:var(--fg); border:1px solid var(--border); padding:8px 10px; border-radius:12px }
    .res-list{ list-style:none; padding:0; margin:0; display:grid; gap:10px }
    .res-item{ padding:12px; border-radius:14px; border:1px solid var(--border); background:#0f1115; }
    .muted{ color:var(--muted) }

    /* ===== PROMOS ===== */
    .promos-wrap{ position:relative; margin:16px 0 6px; }
    .promo-banner{
      position:relative; overflow:hidden; border-radius:18px; border:1px solid var(--border);
      display:flex; align-items:center; gap:16px; padding:14px;
      min-height:120px;
      animation: pulseFlash 1.6s infinite;
    }
    @keyframes pulseFlash {
      0%{ box-shadow: 0 0 0 0 rgba(245,197,66,.35) }
      70%{ box-shadow: 0 0 0 12px rgba(245,197,66,0) }
      100%{ box-shadow: 0 0 0 0 rgba(245,197,66,0) }
    }
    .promo-img{ width:120px; height:120px; object-fit:cover; border-radius:12px; border:1px solid var(--border) }
    .promo-content{ flex:1 1 auto }
    .promo-title{ margin:0 0 6px; font-size:18px; font-weight:800 }
    .promo-desc{ margin:0; color:#d8dee9; opacity:.85; font-size:14px }
    .promo-cta{ margin-left:auto; }
    .promo-cta a{ display:inline-block; padding:10px 14px; border-radius:12px; font-weight:800; text-decoration:none; }
    .promos-dots{ display:flex; gap:6px; justify-content:center; margin-top:8px }
    .promos-dots .dot{ width:8px; height:8px; border-radius:50%; background:#ffffff33 }
    .promos-dots .dot.active{ background:#fff }

    /* ===== RUTINAS ===== */
    .noti-list{ list-style:none; margin:0; padding:0; display:grid; gap:10px }
    .noti-item{ display:flex; gap:12px; align-items:center; padding:12px; border:1px solid var(--border); border-radius:14px; background:#0f1115 }
    .noti-dot{ width:10px; height:10px; border-radius:50%; background:#22c55e; flex:0 0 auto; }
    .noti-dot.visto{ background:#475569 }
    .noti-name{ font-weight:700 }
    .chip{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #334155;background:#0f172a;font-size:.8rem}
    .actions a, .actions button{display:inline-block;padding:6px 10px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#e5e7eb;text-decoration:none}
    .viewer{display:none;margin-top:10px;width:100%}
    .viewer.open{display:block}
    .viewer iframe{width:100%;height:520px;border:0;border-radius:12px}
    .viewer img{max-width:100%;height:auto;border-radius:12px;border:1px solid var(--border)}
  </style>
</head>
<body>
  <div class="container">

    <!-- ===== Promociones (Flash) ===== -->
    <?php if (!empty($promos)): ?>
      <section class="promos-wrap">
        <div id="promo-slide" class="promo-banner" style="background: <?= h($promos[0]['color_fondo']) ?>; color: <?= h($promos[0]['color_texto']) ?>">
          <?php if (!empty($promos[0]['imagen_resuelta'])): ?>
            <img class="promo-img" src="<?= h($promos[0]['imagen_resuelta']) ?>" alt="<?= h($promos[0]['titulo'] ?? 'Promo') ?>">
          <?php endif; ?>
          <div class="promo-content">
            <h3 class="promo-title"><?= h($promos[0]['titulo'] ?? 'Promoción') ?></h3>
            <?php if (!empty($promos[0]['descripcion'])): ?>
              <p class="promo-desc"><?= nl2br(h($promos[0]['descripcion'])) ?></p>
            <?php endif; ?>
          </div>
          <?php if (!empty($promos[0]['link_url'])): ?>
            <div class="promo-cta">
              <a href="<?= h($promos[0]['link_url']) ?>" target="_blank" rel="noopener"
                 style="background:#fff;color:#111;border:0">Ver más</a>
            </div>
          <?php endif; ?>
        </div>
        <?php if (count($promos) > 1): ?>
          <div class="promos-dots" id="promo-dots">
            <?php foreach ($promos as $i=>$p): ?>
              <div class="dot <?= $i===0?'active':'' ?>"></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <!-- Encabezado -->
    <section class="glass header">
      <div>
        <img class="avatar" src="<?= h($foto_url) ?>" alt="Foto de <?= h($cliente_nombre ?: 'Cliente') ?>">
        <div class="badge"><?= h($cliente['estado'] ?? 'Activo') ?></div>
      </div>
      <div style="flex:1 1 auto">
        <h1 class="title">👋 Bienvenido, <span style="color:var(--acc)"><?= h($cliente_nombre ?: 'Cliente') ?></span></h1>
        <p class="subtitle">Miembro desde: <?= h($cliente['fecha_alta'] ?? '—') ?> · Gimnasio #<?= (int)$gimnasio_id ?></p>
      </div>
      <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:center">
        <a class="btn" href="cliente_editar.php">Editar perfil</a>
        <a class="btn" href="generar_qr_individual.php?id=<?= (int)$cliente['id'] ?>" target="_blank" rel="noopener">📲 Mi QR</a>
        <a class="btn" href="cliente_acceso.php?logout=1">🚪 Salir</a>
      </div>
    </section>

    <!-- Alerta membresía -->
    <?= $alerta_membresia_html ?>

    <!-- Tarjetas principales -->
    <section class="grid">
      <!-- Datos personales -->
      <article class="glass card">
        <h2>Tus datos</h2>
        <div class="dl">
          <div class="row"><span>DNI</span><span class="val"><?= h($cliente['dni'] ?? '—') ?></span></div>
          <div class="row"><span>Email</span><span class="val"><?= h($cliente['email'] ?? '—') ?></span></div>
          <div class="row"><span>Teléfono</span><span class="val"><?= h($cliente['telefono'] ?? '—') ?></span></div>
          <div class="row"><span>Disciplina</span><span class="val"><?= h($cliente['disciplina'] ?? '—') ?></span></div>
          <div class="row"><span>Estado</span><span class="val"><?= h($cliente['estado'] ?? '—') ?></span></div>
        </div>
      </article>

      <!-- Accesos rápidos -->
      <article class="glass card">
        <h2>Accesos rápidos</h2>
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px">
          <a class="btn" href="ver_mis_pagos.php">💳 Mis pagos</a>
          <a class="btn" href="pago_online.php">🌐 Pagos online</a>
          <a class="btn" href="ver_turnos_cliente.php">📅 Sacar turnos</a>
          <a class="btn" href="form_progreso.php">📈 Progreso</a>
        </div>
      </article>

      <!-- Reservas del día -->
      <article class="glass card">
        <h2>📋 Reservas del día</h2>
        <form class="filter" method="GET">
          <label class="muted">🗓</label>
          <input type="date" name="fecha" value="<?= h($fecha_filtro) ?>" onchange="this.form.submit()">
        </form>
        <ul id="contenedor-reservas" class="res-list"><li class="muted">Cargando reservas...</li></ul>
      </article>

      <!-- ===== Rutinas / Archivos enviados por tu profesor ===== -->
      <article class="glass card col-span-2">
        <h2>📄 Rutinas y Archivos del Profesor</h2>
        <?php if (empty($rutinas)): ?>
          <p class="muted">No tenés rutinas/archivos disponibles por ahora.</p>
        <?php else: ?>
          <ul class="noti-list" id="lista-rutinas" style="max-height:480px; overflow:auto">
            <?php foreach ($rutinas as $n): 
              $ext = strtolower((string)($n['extension'] ?? ''));
              $url = (string)$n['url_archivo'];
              $viewerUrl = cld_viewer_url($url, $ext);
              $rid = (int)$n['id'];
            ?>
            <li class="noti-item" data-rid="<?= $rid ?>">
              <span class="noti-dot <?= $n['visto'] ? 'visto':'' ?>"></span>
              <div style="flex:1 1 auto">
                <div class="noti-name"><?= h($n['nombre_archivo']) ?></div>
                <div class="muted" style="font-size:.9rem">
                  <span class="chip"><?= strtoupper(h($ext ?: 'archivo')) ?></span>
                  · <?= h(fmt_bytes($n['tamano_bytes'])) ?>
                  · Subido por <?= h($n['profesor']) ?>
                  · <span class="muted"><?= h($n['creado_en']) ?></span>
                </div>

                <!-- Visor inline opcional -->
                <div id="viewer-<?= $rid ?>" class="viewer">
                  <?php if (is_pdf_ext($ext)): ?>
                    <iframe src="<?= h($viewerUrl) ?>" title="PDF"></iframe>
                  <?php elseif (is_image_ext($ext)): ?>
                    <img src="<?= h($url) ?>" alt="<?= h($n['nombre_archivo']) ?>">
                  <?php else: ?>
                    <div class="muted">Vista previa no disponible para este tipo de archivo.</div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="actions">
                <a href="<?= h($url) ?>" target="_blank" rel="noopener">Ver/Descargar</a>
                <?php if (is_pdf_ext($ext) || is_image_ext($ext)): ?>
                  <button type="button" onclick="toggleViewer(<?= $rid ?>)">Ver aquí</button>
                <?php endif; ?>
                <?php if (!$n['visto']): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="rutina_id" value="<?= $rid ?>">
                    <button type="submit" name="marcar_visto">Marcar visto</button>
                  </form>
                <?php endif; ?>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>

          <!-- Mostrar más / menos sin salir del panel -->
          <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap">
            <?php if (!isset($_GET['todo']) || $_GET['todo'] != '1'): ?>
              <a class="btn" href="panel_cliente.php?todo=1">📁 Ver historial completo</a>
            <?php else: ?>
              <a class="btn" href="panel_cliente.php">⬆️ Mostrar menos</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </article>
    </section>

  </div>

  <script>
  // ======== Slider simple de Promos ========
  (function(){
    const promos = <?= json_encode($promos, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    if (!Array.isArray(promos) || promos.length === 0) return;

    const slide = document.getElementById('promo-slide');
    const dots  = document.getElementById('promo-dots')?.querySelectorAll('.dot') || [];
    let idx = 0;
    const render = (i) => {
      const p = promos[i];
      if (!p || !slide) return;
      slide.style.background = p.color_fondo || '#111111';
      slide.style.color      = p.color_texto || '#FFD700';

      let html = '';
      if (p.imagen_resuelta) {
        html += `<img class="promo-img" src="${p.imagen_resuelta}" alt="${(p.titulo||'Promo').replace(/"/g,'&quot;')}">`;
      }
      html += `<div class="promo-content">
        <h3 class="promo-title">${p.titulo ? p.titulo : 'Promoción'}</h3>`;
      if (p.descripcion) {
        html += `<p class="promo-desc">${p.descripcion.replace(/\n/g,'<br>')}</p>`;
      }
      html += `</div>`;
      if (p.link_url) {
        html += `<div class="promo-cta"><a href="${p.link_url}" target="_blank" rel="noopener" style="background:#fff;color:#111;border:0">Ver más</a></div>`;
      }
      slide.innerHTML = html;

      if (dots.length) {
        dots.forEach((d,di)=> d.classList.toggle('active', di===i));
      }
    };

    render(0);
    if (promos.length > 1) {
      setInterval(()=>{ idx = (idx+1) % promos.length; render(idx); }, 6000);
    }
  })();

  // ======== Reservas del día (AJAX) ========
  document.addEventListener('DOMContentLoaded', () => {
    const ulReservas = document.getElementById('contenedor-reservas');
    const fecha = '<?= h($fecha_filtro) ?>';

    fetch(`reservas_cliente_ajax.php?fecha=${encodeURIComponent(fecha)}`)
      .then(res => res.ok ? res.json() : Promise.reject())
      .then(data => {
        if (!Array.isArray(data) || data.length === 0) {
          ulReservas.innerHTML = '<li class="res-item muted">No hay reservas para este día.</li>';
          return;
        }
        ulReservas.innerHTML = '';
        data.forEach(r => {
          const li = document.createElement('li');
          li.className = 'res-item';
          li.innerHTML = `
            <div><strong>📅 ${r.dia_semana || ''}</strong> · <span class="muted">🕒 ${r.hora_inicio || ''}</span></div>
            <div class="muted" style="margin-top:6px">👤 ${r.cliente_apellido || ''} ${r.cliente_nombre || ''}</div>
            <div class="muted">👨‍🏫 ${r.profesor_apellido || ''} ${r.profesor_nombre || ''}</div>
          `;
          ulReservas.appendChild(li);
        });
      })
      .catch(() => {
        ulReservas.innerHTML = '<li class="res-item" style="color:#ff6b6b">Error al cargar reservas.</li>';
      });
  });

  // ======== Visor inline (PDF/Imagen) ========
  function toggleViewer(id){
    const el = document.getElementById('viewer-'+id);
    if (!el) return;
    el.classList.toggle('open');
  }
  </script>
</body>
</html>
